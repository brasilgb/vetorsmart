<?php

namespace App\Services\Billing;

use App\Enums\BillingSource;
use App\Enums\ChargeStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BillingCharge;
use App\Models\BillingSetting;
use App\Models\Subscription;
use App\Services\Billing\Efi\EfiException;
use App\Services\OperationalNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The daily billing routine. Every step is idempotent, so running it twice, or after a day without running, gives
 * the same result:
 *
 * 1. retry the issue of pending charges and reconcile the open ones with Efí (recovery after an outage);
 * 2. trial or paid period ended: cancel (if requested), or open the grace period (past due);
 * 3. grace period ended without payment: financial suspension;
 * 4. renewal window: open the charge of the next period for subscriptions with a payment method;
 * 5. warn the owners three days before the trial ends.
 *
 * While billing is not in force (BillingEnforcement), the routine changes nothing: no trial ends, no grace, no
 * suspension, no charge, no notice. The decision is logged on every run, so the operator sees why nothing happened.
 */
class BillingCycle
{
    /** Pending charges are retried up to this many issue attempts; then only the RootAdmin retries. */
    private const int MAX_ISSUE_ATTEMPTS = 10;

    public function __construct(
        private SubscriptionService $subscriptions,
        private ChargeService $charges,
        private OperationalNotifier $notifier,
        private BillingEnforcement $enforcement,
    ) {}

    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        $report = ['enforcement' => 0, 'issued' => 0, 'reconciled' => 0, 'gateway_errors' => 0, 'past_due' => 0, 'suspended' => 0, 'cancelled' => 0, 'renewals' => 0, 'trial_warnings' => 0];

        if (! $this->enforcement->active()) {
            Log::info('Rotina de assinaturas sem efeito: a cobrança não está em vigor.', [
                'enabled_by_environment' => $this->enforcement->enabledByEnvironment(),
                'subscriptions' => Subscription::query()->count(),
            ]);

            return $report;
        }

        $report['enforcement'] = 1;
        $this->retryCharges($report);

        Subscription::query()->with(['plan', 'tenant'])->orderBy('id')->chunkById(100, function ($subscriptions) use (&$report): void {
            foreach ($subscriptions as $subscription) {
                try {
                    $this->advance($subscription, $report);
                } catch (Throwable $exception) {
                    Log::error('Rotina de assinatura falhou para uma empresa.', ['subscription_id' => $subscription->getKey(), 'error' => $exception->getMessage()]);
                }
            }
        });

        return $report;
    }

    /**
     * @param  array<string, int>  $report
     */
    private function retryCharges(array &$report): void
    {
        BillingCharge::query()
            ->whereIn('status', [ChargeStatus::Pending->value, ChargeStatus::Waiting->value])
            ->orderBy('id')
            ->each(function (BillingCharge $charge) use (&$report): void {
                try {
                    if ($charge->status === ChargeStatus::Pending) {
                        if ($charge->issue_attempts < self::MAX_ISSUE_ATTEMPTS && $this->charges->issue($charge)->status === ChargeStatus::Waiting) {
                            $report['issued']++;
                        }

                        return;
                    }

                    $this->charges->reconcile($charge);
                    $report['reconciled']++;
                } catch (EfiException $exception) {
                    $report['gateway_errors']++;
                    Log::warning('Conciliação da cobrança adiada.', ['charge_id' => $charge->getKey(), 'error' => $exception->getMessage()]);
                }
            });
    }

    /**
     * @param  array<string, int>  $report
     */
    private function advance(Subscription $subscription, array &$report): void
    {
        $settings = BillingSetting::current();

        $periodEnd = match ($subscription->status) {
            SubscriptionStatus::Trialing => $subscription->trial_ends_at,
            SubscriptionStatus::Active => $subscription->current_period_end,
            default => null,
        };

        if ($periodEnd !== null && $periodEnd->isPast()) {
            if ($subscription->cancel_at_period_end) {
                $this->subscriptions->transition($subscription, SubscriptionStatus::Cancelled, BillingSource::System, null, 'Cancelamento solicitado pela empresa, ao fim do período.', ['cancelled_at' => now(), 'cancel_at_period_end' => false]);
                $report['cancelled']++;

                return;
            }

            $subscription = $this->subscriptions->transition($subscription, SubscriptionStatus::PastDue, BillingSource::System, null, 'Período encerrado sem pagamento.', ['grace_ends_at' => $this->subscriptions->graceFrom($periodEnd)]);
            $report['past_due']++;
            $this->openCharge($subscription, $report);

            return;
        }

        if ($subscription->status === SubscriptionStatus::PastDue && $subscription->grace_ends_at !== null && $subscription->grace_ends_at->isPast()) {
            $this->subscriptions->transition($subscription, SubscriptionStatus::Suspended, BillingSource::System, null, 'Carência encerrada sem pagamento.');
            $report['suspended']++;

            return;
        }

        if ($periodEnd !== null && ! $subscription->cancel_at_period_end && $periodEnd->copy()->subDays($settings->renewal_days_before)->isPast()) {
            $this->openCharge($subscription, $report);
        }

        if ($subscription->status === SubscriptionStatus::Trialing && $periodEnd !== null && $periodEnd->copy()->subDays(3)->isPast()) {
            $this->notifier->billingNotice($subscription->tenant, "trial-ending:{$subscription->getKey()}:{$periodEnd->format('Ymd')}", 'Seu período de teste termina em breve', 'O teste termina em '.$periodEnd->format('d/m/Y').'. Escolha a forma de pagamento em Minha Assinatura para continuar sem interrupção.');
            $report['trial_warnings']++;
        }
    }

    /**
     * Open the charge of the next period when the company already chose how to pay and the plan has a price.
     *
     * @param  array<string, int>  $report
     */
    private function openCharge(Subscription $subscription, array &$report): void
    {
        if ($subscription->payment_method === null) {
            return;
        }

        try {
            $before = BillingCharge::query()->where('subscription_id', $subscription->getKey())->count();
            $this->charges->openFor($subscription, $subscription->payment_method);

            if (BillingCharge::query()->where('subscription_id', $subscription->getKey())->count() > $before) {
                $report['renewals']++;
            }
        } catch (ValidationException) {
            // Plan without a price yet: nothing to charge.
        }
    }
}
