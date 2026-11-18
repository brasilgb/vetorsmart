<?php

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Enums\BillingSource;
use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\BillingCharge;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OperationalNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change of a subscription: trial, contract, renewal, grace, financial suspension, reactivation and
 * cancellation. Each change runs under a row lock, checks the allowed transition, writes the history
 * (subscription_events) and the audit log. The administrative suspension (tenants.status) is never touched here.
 */
class SubscriptionService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private OperationalNotifier $notifier,
    ) {}

    /**
     * Start the trial of a company. A company that already has a subscription keeps it (idempotent).
     */
    public function startTrial(Tenant $tenant, BillingSource $source, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($tenant, $source, $actor): Subscription {
            Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->first();

            $existing = Subscription::query()->forTenant($tenant)->first();

            if ($existing !== null) {
                return $existing;
            }

            $plan = Plan::main();
            $subscription = new Subscription;
            $subscription->forceFill([
                'tenant_id' => $tenant->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays($plan->trial_days),
            ])->save();

            $this->record($subscription, 'trial_started', null, SubscriptionStatus::Trialing, $source, $actor, "Teste de {$plan->trial_days} dias.");

            return $subscription;
        });
    }

    /**
     * Move the subscription to another status. Transitions outside SubscriptionStatus::allowedTargets() are refused.
     *
     * @param  array<string, mixed>  $changes
     */
    public function transition(Subscription $subscription, SubscriptionStatus $target, BillingSource $source, ?User $actor = null, ?string $note = null, array $changes = []): Subscription
    {
        return DB::transaction(function () use ($subscription, $target, $source, $actor, $note, $changes): Subscription {
            $subscription = $this->locked($subscription);
            $from = $subscription->status;

            if ($from === $target) {
                $subscription->forceFill($changes)->save();

                return $subscription;
            }

            if (! in_array($target, $from->allowedTargets(), true)) {
                throw ValidationException::withMessages(['status' => ["Mudança não permitida: {$from->label()} → {$target->label()}."]]);
            }

            $subscription->forceFill([...$changes, 'status' => $target])->save();

            $this->record($subscription, 'status_changed', $from, $target, $source, $actor, $note);
            $this->notifyStatus($subscription, $from, $target);

            return $subscription;
        });
    }

    /**
     * A paid charge: the subscription becomes active up to the end of the paid period. A payment for an older
     * period, confirmed late, never moves the period back.
     */
    public function periodPaid(BillingCharge $charge, BillingSource $source, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($charge, $source, $actor): Subscription {
            $subscription = $this->locked($charge->subscription);

            $currentEnd = $subscription->current_period_end;
            $extends = $currentEnd === null || $charge->period_end->gt($currentEnd);

            // A late payment for a period that already ended (regularization after the grace or the suspension)
            // buys a full period from the payment date, so the company is not reactivated into an expired period.
            [$start, $end] = $charge->period_end->isPast()
                ? [now(), now()->addMonths($charge->billing_interval->months())]
                : [$charge->period_start, $charge->period_end];

            $changes = $extends
                ? ['current_period_start' => $start, 'current_period_end' => $end, 'grace_ends_at' => null]
                : [];

            $stillCovered = ($extends ? $end : $currentEnd)->isFuture();

            if ($subscription->status === SubscriptionStatus::Active || ! $stillCovered) {
                $subscription->forceFill($changes)->save();
                $this->record($subscription, 'charge_paid', $subscription->status, $subscription->status, $source, $actor, "Cobrança #{$charge->id} paga.");

                return $subscription;
            }

            if ($subscription->status === SubscriptionStatus::Cancelled) {
                $changes['cancelled_at'] = null;
                $changes['cancel_at_period_end'] = false;
            }

            return $this->transition($subscription, SubscriptionStatus::Active, $source, $actor, "Cobrança #{$charge->id} paga.", $changes);
        });
    }

    public function choosePaymentMethod(Subscription $subscription, PaymentMethod $method, User $actor): Subscription
    {
        return DB::transaction(function () use ($subscription, $method, $actor): Subscription {
            $subscription = $this->locked($subscription);

            if ($subscription->payment_method !== $method) {
                $subscription->forceFill(['payment_method' => $method])->save();
                $this->record($subscription, 'payment_method', $subscription->status, $subscription->status, BillingSource::Company, $actor, 'Forma de pagamento: '.$method->label().'.');
            }

            return $subscription;
        });
    }

    /**
     * Change of billing cycle requested by the company. Rules (no proportional charge or credit is ever generated):
     *
     * - nothing paid yet and no open charge (trial, or contracting again): the new cycle applies at once;
     * - a charge is open: refused, the company pays or waits for it to expire first, so two charges never overlap;
     * - a paid period is running: the change is scheduled and applies to the next period, when its charge is created.
     *
     * Choosing the current cycle again withdraws a scheduled change.
     */
    public function changeInterval(Subscription $subscription, BillingInterval $interval, User $actor): Subscription
    {
        return DB::transaction(function () use ($subscription, $interval, $actor): Subscription {
            $subscription = $this->locked($subscription);

            if ($subscription->plan->priceFor($interval) === null) {
                throw ValidationException::withMessages(['interval' => ["O ciclo {$interval->label()} ainda não tem valor definido."]]);
            }

            if ($interval === $subscription->billing_interval) {
                if ($subscription->scheduled_interval !== null) {
                    $subscription->forceFill(['scheduled_interval' => null])->save();
                    $this->record($subscription, 'interval_change_withdrawn', $subscription->status, $subscription->status, BillingSource::Company, $actor, "Mantido o ciclo {$interval->label()}.");
                }

                return $subscription;
            }

            $hasOpenCharge = BillingCharge::query()->where('subscription_id', $subscription->getKey())
                ->whereIn('status', [ChargeStatus::Pending->value, ChargeStatus::Waiting->value])
                ->exists();

            if ($hasOpenCharge) {
                throw ValidationException::withMessages(['interval' => ['Há uma cobrança em aberto. Pague-a ou aguarde o vencimento antes de trocar o ciclo.']]);
            }

            $periodRunning = $subscription->status === SubscriptionStatus::Active && $subscription->current_period_end?->isFuture();

            if ($periodRunning) {
                $subscription->forceFill(['scheduled_interval' => $interval])->save();
                $this->record($subscription, 'interval_scheduled', $subscription->status, $subscription->status, BillingSource::Company, $actor, "Ciclo {$interval->label()} a partir do próximo período.");
            } else {
                $subscription->forceFill(['billing_interval' => $interval, 'scheduled_interval' => null, 'price_cents' => null, 'extra_user_price_cents' => null])->save();
                $this->record($subscription, 'interval_changed', $subscription->status, $subscription->status, BillingSource::Company, $actor, "Ciclo {$interval->label()}.");
            }

            $this->auditLogger->record('subscription.interval_changed', $subscription->tenant, $subscription, ['interval' => $interval->value, 'scheduled' => $periodRunning], $actor);

            return $subscription;
        });
    }

    /**
     * Prepare a subscription, already locked by the caller, for the charge of its next period: apply a scheduled
     * cycle change and freeze the contract terms that are still open (cycle price, extra-user price and the plan
     * promotion valid today). Later changes of the plan never alter frozen terms.
     */
    public function prepareForCharge(Subscription $subscription): Subscription
    {
        $plan = $subscription->plan;
        $changes = [];
        $notes = [];

        if ($subscription->scheduled_interval !== null) {
            $changes += ['billing_interval' => $subscription->scheduled_interval, 'scheduled_interval' => null, 'price_cents' => null, 'extra_user_price_cents' => null];
            $notes[] = "Ciclo {$subscription->scheduled_interval->label()} aplicado.";
            $subscription->forceFill($changes);
        }

        if ($subscription->price_cents === null && ($price = $plan->priceFor($subscription->billing_interval)) !== null) {
            $changes['price_cents'] = $price;
            $notes[] = 'Valor '.$subscription->billing_interval->label().' congelado: '.$this->money($price).'.';

            if ($subscription->discount_percent === 0 && ($promotion = $plan->activePromotionPercent()) > 0) {
                $changes += ['discount_percent' => $promotion, 'discount_until' => $plan->promotional_until];
                $notes[] = "Promoção de {$promotion}%.";
            }
        }

        if ($subscription->extra_user_price_cents === null && $plan->extra_user_price_cents !== null) {
            $changes['extra_user_price_cents'] = $plan->extra_user_price_cents;
        }

        if ($changes === []) {
            return $subscription;
        }

        $subscription->forceFill($changes)->save();
        $this->record($subscription, 'terms_frozen', $subscription->status, $subscription->status, BillingSource::System, null, implode(' ', $notes) ?: 'Condições do contrato registradas.');

        return $subscription;
    }

    /**
     * Cancellation requested by the company: access continues until the end of the paid period (or of the trial).
     */
    public function requestCancellation(Subscription $subscription, User $actor): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $subscription = $this->locked($subscription);

            if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
                throw ValidationException::withMessages(['status' => ['Só é possível agendar o cancelamento de uma assinatura em teste ou ativa.']]);
            }

            $subscription->forceFill(['cancel_at_period_end' => true])->save();
            $this->record($subscription, 'cancellation_requested', $subscription->status, $subscription->status, BillingSource::Company, $actor, 'Cancelamento ao fim do período.');
            $this->auditLogger->record('subscription.cancellation_requested', $subscription->tenant, $subscription, [], $actor);

            return $subscription;
        });
    }

    public function resume(Subscription $subscription, User $actor): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $subscription = $this->locked($subscription);

            if ($subscription->cancel_at_period_end) {
                $subscription->forceFill(['cancel_at_period_end' => false])->save();
                $this->record($subscription, 'cancellation_withdrawn', $subscription->status, $subscription->status, BillingSource::Company, $actor, 'Cancelamento desfeito.');
            }

            return $subscription;
        });
    }

    public function extendTrial(Subscription $subscription, int $days, User $actor): Subscription
    {
        return DB::transaction(function () use ($subscription, $days, $actor): Subscription {
            $subscription = $this->locked($subscription);

            if ($subscription->status !== SubscriptionStatus::Trialing) {
                throw ValidationException::withMessages(['days' => ['Só é possível prorrogar uma assinatura em teste.']]);
            }

            $subscription->forceFill(['trial_ends_at' => ($subscription->trial_ends_at ?? now())->copy()->addDays($days)])->save();
            $this->record($subscription, 'trial_extended', $subscription->status, $subscription->status, BillingSource::RootAdmin, $actor, "Teste prorrogado em {$days} dia(s).");
            $this->auditLogger->record('subscription.trial_extended', $subscription->tenant, $subscription, ['days' => $days], $actor);

            return $subscription;
        });
    }

    /**
     * Contract terms set by the RootAdmin: a negotiated price (null follows the plan) and a discount with end date.
     */
    public function setTerms(Subscription $subscription, ?int $priceCents, int $discountPercent, ?CarbonInterface $discountUntil, User $actor): Subscription
    {
        return DB::transaction(function () use ($subscription, $priceCents, $discountPercent, $discountUntil, $actor): Subscription {
            $subscription = $this->locked($subscription);

            $subscription->forceFill(['price_cents' => $priceCents, 'discount_percent' => $discountPercent, 'discount_until' => $discountUntil])->save();
            $this->record($subscription, 'terms_changed', $subscription->status, $subscription->status, BillingSource::RootAdmin, $actor, sprintf('Valor: %s; desconto: %d%%%s.', $priceCents === null ? 'do plano' : $this->money($priceCents), $discountPercent, $discountUntil ? ' até '.$discountUntil->format('d/m/Y') : ''));
            $this->auditLogger->record('subscription.terms_changed', $subscription->tenant, $subscription, ['price_cents' => $priceCents, 'discount_percent' => $discountPercent], $actor);

            return $subscription;
        });
    }

    public function cancelNow(Subscription $subscription, User $actor, string $reason): Subscription
    {
        $subscription = $this->transition($subscription, SubscriptionStatus::Cancelled, BillingSource::RootAdmin, $actor, $reason, ['cancelled_at' => now(), 'cancel_at_period_end' => false]);
        $this->auditLogger->record('subscription.cancelled', $subscription->tenant, $subscription, ['reason' => $reason], $actor);

        return $subscription;
    }

    /**
     * The grace period after an unpaid period, from the billing settings.
     */
    public function graceFrom(CarbonInterface $from): CarbonInterface
    {
        return $from->copy()->addDays(BillingSetting::current()->grace_days);
    }

    private function money(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    private function locked(Subscription $subscription): Subscription
    {
        return Subscription::query()->with(['plan', 'tenant'])->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
    }

    private function record(Subscription $subscription, string $type, ?SubscriptionStatus $from, ?SubscriptionStatus $to, BillingSource $source, ?User $actor, ?string $note): void
    {
        $event = new SubscriptionEvent;
        $event->forceFill([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'source' => $source,
            'note' => $note === null ? null : mb_substr($note, 0, 500),
            'user_id' => $actor?->getKey(),
        ])->save();
    }

    private function notifyStatus(Subscription $subscription, SubscriptionStatus $from, SubscriptionStatus $to): void
    {
        $tenant = $subscription->tenant;
        $key = "{$subscription->getKey()}:{$to->value}:".now()->format('YmdHis');

        match ($to) {
            SubscriptionStatus::PastDue => $this->notifier->billingNotice($tenant, $key, 'Pagamento da assinatura pendente', 'Regularize até '.$subscription->grace_ends_at?->format('d/m/Y').' para manter o acesso ao painel.'),
            SubscriptionStatus::Suspended => $this->notifier->billingNotice($tenant, $key, 'Assinatura suspensa por falta de pagamento', 'O painel fica bloqueado até a regularização. Acesse Minha Assinatura para pagar.'),
            SubscriptionStatus::Active => in_array($from, [SubscriptionStatus::PastDue, SubscriptionStatus::Suspended, SubscriptionStatus::Cancelled], true)
                ? $this->notifier->billingNotice($tenant, $key, 'Assinatura regularizada', 'Pagamento confirmado. O acesso ao painel está normal.')
                : null,
            SubscriptionStatus::Cancelled => $this->notifier->billingNotice($tenant, $key, 'Assinatura cancelada', 'O acesso ao painel foi encerrado. Para voltar, contrate novamente em Minha Assinatura.'),
            default => null,
        };
    }
}
