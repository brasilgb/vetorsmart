<?php

namespace App\Services\Billing;

use App\Enums\BillingSource;
use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\BillingCharge;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\Efi\EfiChargesClient;
use App\Services\Billing\Efi\EfiException;
use App\Services\Billing\Efi\EfiPixClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Charges of the subscription periods. A charge is first written locally (one per subscription and period, by a
 * unique key), then issued at Efí outside the database transaction, so a slow or unavailable gateway never holds a
 * lock and a failed issue is retried later with the same identifiers. Payment is always confirmed by querying Efí,
 * never by trusting a notice, and settling is idempotent.
 */
class ChargeService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private EfiPixClient $pix,
        private EfiChargesClient $charges,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * The charge of the period the company has to pay now. An open charge of the subscription is returned as is;
     * otherwise one is created for the next unpaid period and issued.
     */
    public function openFor(Subscription $subscription, PaymentMethod $method): BillingCharge
    {
        $charge = DB::transaction(function () use ($subscription, $method): BillingCharge {
            $subscription = Subscription::query()->with('plan')->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

            $open = BillingCharge::query()->where('subscription_id', $subscription->getKey())
                ->whereIn('status', [ChargeStatus::Pending->value, ChargeStatus::Waiting->value])
                ->orderBy('period_start')
                ->first();

            if ($open !== null) {
                return $open;
            }

            $start = $this->nextPeriodStart($subscription);
            $charge = BillingCharge::query()->where('subscription_id', $subscription->getKey())->where('period_start', $start)->first();

            if ($charge !== null) {
                return $charge;
            }

            $subscription = $this->subscriptions->prepareForCharge($subscription);
            $quote = $subscription->quote();

            if ($quote === null) {
                throw ValidationException::withMessages(['plan' => ['O valor do plano ainda não foi definido pela VetorSmart. A contratação será liberada em breve.']]);
            }

            $charge = new BillingCharge;
            $charge->forceFill([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->getKey(),
                'period_start' => $start,
                'period_end' => $start->copy()->addMonths($quote['months']),
                'billing_interval' => $quote['interval'],
                'amount_cents' => $quote['total_cents'],
                'base_amount_cents' => $quote['base_cents'],
                'discount_cents' => $quote['discount_cents'],
                'extra_users' => $quote['extra_users'],
                'extra_users_amount_cents' => $quote['extra_users_cents'],
                'due_date' => $start->isPast() ? now()->addDays(BillingSetting::current()->billet_due_days) : $start,
                'status' => ChargeStatus::Pending,
                'method' => $method,
            ])->save();

            return $charge;
        });

        return $charge->status === ChargeStatus::Pending ? $this->issue($charge) : $charge;
    }

    /**
     * Issue a pending charge at Efí. A failure keeps the charge pending with the error, to be retried.
     */
    public function issue(BillingCharge $charge): BillingCharge
    {
        if ($charge->status !== ChargeStatus::Pending) {
            return $charge;
        }

        if (BillingSetting::current()->isProduction() && ! Plan::main()->pricesApproved()) {
            $charge->forceFill(['last_error' => 'Valores comerciais ainda não aprovados: nenhuma cobrança real é emitida.'])->save();

            return $charge;
        }

        $charge->forceFill(['issue_attempts' => $charge->issue_attempts + 1])->save();

        try {
            $result = $charge->method === PaymentMethod::Pix ? $this->issuePix($charge) : $this->issueBillet($charge);
        } catch (EfiException $exception) {
            $charge->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();
            Log::warning('Cobrança da assinatura não emitida.', ['charge_id' => $charge->getKey(), 'error' => $exception->getMessage()]);

            return $charge;
        }

        $charge->forceFill([...$result, 'status' => ChargeStatus::Waiting, 'last_error' => null])->save();

        return $charge;
    }

    /**
     * Read the current state at Efí and apply it. Paid is final: a later state never reverts it.
     */
    public function reconcile(BillingCharge $charge): BillingCharge
    {
        if ($charge->gateway_id === null || $charge->status === ChargeStatus::Paid) {
            return $charge;
        }

        if ($charge->method === PaymentMethod::Pix) {
            $remote = $this->pix->getCharge($charge->gateway_id);

            if (($remote['txid'] ?? null) !== $charge->gateway_id) {
                throw new EfiException('Efí: a cobrança Pix consultada não corresponde à cobrança local.');
            }

            $status = (string) ($remote['status'] ?? '');

            if ($status === 'CONCLUIDA') {
                $received = array_sum(array_map(fn ($item): int => (int) round(((float) ($item['valor'] ?? 0)) * 100), (array) ($remote['pix'] ?? [])));

                return $this->settle($charge, $received, BillingSource::Gateway);
            }

            if (in_array($status, ['REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP'], true)) {
                return $this->close($charge, "Pix {$status}.");
            }

            return $charge;
        }

        $remote = $this->charges->getCharge($charge->gateway_id);
        $status = (string) ($remote['status'] ?? '');

        if (in_array($status, ['paid', 'settled'], true)) {
            return $this->settle($charge, (int) ($remote['paid_value'] ?? $remote['total'] ?? 0), BillingSource::Gateway);
        }

        if (in_array($status, ['canceled', 'expired', 'unpaid'], true)) {
            return $this->close($charge, "Boleto {$status}.");
        }

        if (in_array($status, ['refunded', 'contested'], true)) {
            Log::critical('Cobrança da assinatura estornada ou contestada; requer conciliação manual.', ['charge_id' => $charge->getKey(), 'status' => $status]);
        }

        return $charge;
    }

    /**
     * Payment registered by the RootAdmin (transfer received outside Efí, for example). Audited with the reason.
     */
    public function settleManually(BillingCharge $charge, User $actor, string $reason): BillingCharge
    {
        $charge = $this->settle($charge, $charge->amount_cents, BillingSource::RootAdmin, $actor, 'manual');
        $this->auditLogger->record('billing.charge_settled_manually', $charge->tenant, $charge, ['reason' => $reason], $actor);

        return $charge;
    }

    /**
     * Settle once. A second confirmation of the same charge changes nothing; an amount below the charge does not
     * settle it and is reported for manual reconciliation.
     */
    private function settle(BillingCharge $charge, int $receivedCents, BillingSource $source, ?User $actor = null, string $via = 'efi'): BillingCharge
    {
        $settled = DB::transaction(function () use ($charge, $receivedCents, $via): ?BillingCharge {
            $charge = BillingCharge::query()->whereKey($charge->getKey())->lockForUpdate()->firstOrFail();

            if ($charge->status === ChargeStatus::Paid) {
                return null;
            }

            if ($receivedCents < $charge->amount_cents) {
                $charge->forceFill(['last_error' => "Valor recebido ({$receivedCents}) menor que o cobrado ({$charge->amount_cents}); conciliação manual."])->save();
                Log::warning('Pagamento menor que a cobrança da assinatura.', ['charge_id' => $charge->getKey()]);

                return null;
            }

            $charge->forceFill(['status' => ChargeStatus::Paid, 'paid_amount_cents' => $receivedCents, 'paid_at' => now(), 'paid_via' => $via, 'last_error' => null])->save();

            return $charge;
        });

        if ($settled === null) {
            return $charge->fresh() ?? $charge;
        }

        $settled->load('subscription.plan');
        $this->subscriptions->periodPaid($settled, $source, $actor);

        return $settled;
    }

    private function close(BillingCharge $charge, string $reason): BillingCharge
    {
        return DB::transaction(function () use ($charge, $reason): BillingCharge {
            $charge = BillingCharge::query()->whereKey($charge->getKey())->lockForUpdate()->firstOrFail();

            if ($charge->status->isOpen()) {
                $charge->forceFill(['status' => ChargeStatus::Cancelled, 'last_error' => $reason])->save();
            }

            return $charge;
        });
    }

    /**
     * Start of the period to charge: the end of the trial or of the paid period; now, when that moment has passed
     * long ago (a cancelled company contracting again).
     */
    private function nextPeriodStart(Subscription $subscription): CarbonInterface
    {
        $start = match ($subscription->status) {
            SubscriptionStatus::Trialing => $subscription->trial_ends_at,
            SubscriptionStatus::Cancelled => null,
            default => $subscription->current_period_end ?? $subscription->trial_ends_at,
        };

        return ($start === null || $start->lt(now()->subMonths(1))) ? now()->startOfMinute() : $start->copy();
    }

    /**
     * @return array<string, mixed>
     */
    private function issuePix(BillingCharge $charge): array
    {
        $txid = $charge->pixTxid();
        $expires = max(86400, (int) now()->diffInSeconds($charge->due_date->copy()->endOfDay()->addDays(BillingSetting::current()->grace_days)));

        try {
            $remote = $this->pix->createImmediateCharge($txid, number_format($charge->amount_cents / 100, 2, '.', ''), $expires, 'Assinatura VetorSmart - '.$charge->period_start->format('m/Y'));
        } catch (EfiException $exception) {
            // A previous attempt may have created it before the connection dropped: the txid is ours, so look it up.
            if ($exception->temporary || $exception->status === null) {
                throw $exception;
            }

            try {
                $remote = $this->pix->getCharge($txid);
            } catch (EfiException) {
                throw $exception;
            }
        }

        return ['gateway_id' => $txid, 'pix_copy_paste' => $remote['pixCopiaECola'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    private function issueBillet(BillingCharge $charge): array
    {
        $tenant = $charge->tenant;
        $document = preg_replace('/\D+/', '', (string) $tenant->document) ?? '';

        if (! in_array(strlen($document), [11, 14], true)) {
            throw new EfiException('Boleto: informe o CPF ou CNPJ da empresa em Minha Assinatura.');
        }

        $customer = strlen($document) === 11
            ? ['name' => $tenant->name, 'cpf' => $document]
            : ['juridical_person' => ['corporate_name' => $tenant->name, 'cnpj' => $document]];

        if (filled($tenant->email)) {
            $customer['email'] = $tenant->email;
        }

        $remote = $this->charges->createBillet(
            [['name' => 'Assinatura VetorSmart '.$charge->period_start->format('m/Y'), 'value' => $charge->amount_cents, 'amount' => 1]],
            $customer,
            $charge->due_date->format('Y-m-d'),
            'vsmart-charge-'.$charge->getKey(),
            $this->notificationUrl(),
        );

        return [
            'gateway_id' => isset($remote['charge_id']) ? (string) $remote['charge_id'] : null,
            'billet_url' => $remote['link'] ?? $remote['billet_link'] ?? null,
            'billet_pdf' => $remote['pdf']['charge'] ?? null,
            'billet_barcode' => $remote['barcode'] ?? null,
            'pix_copy_paste' => $remote['pix']['qrcode'] ?? null,
        ];
    }

    private function notificationUrl(): string
    {
        return rtrim((string) config('platform.public_url'), '/').route('billing.efi.charges', ['secret' => BillingSetting::current()->webhookSecret()], false);
    }
}
