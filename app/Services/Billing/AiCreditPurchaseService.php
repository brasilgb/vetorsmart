<?php

namespace App\Services\Billing;

use App\Enums\AiCreditPurchaseStatus;
use App\Models\AiCreditPackage;
use App\Models\AiCreditPurchase;
use App\Models\BillingSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchases of additional AI credits, a charge separate from the subscription. Real purchases are not enabled in
 * this stage: requesting one requires the RootAdmin switch, the purchase waits for payment without any gateway
 * charge, and payment is confirmed by the RootAdmin (homologation). Rules:
 *
 * - a company has at most one purchase awaiting payment; asking again returns it;
 * - payment credits the purchased bucket once (ledger reference per purchase);
 * - a refund removes only the credits still unused, and the amount is proportional to them;
 * - every action is audited.
 */
class AiCreditPurchaseService
{
    public function __construct(
        private AiCreditService $credits,
        private AuditLogger $auditLogger,
    ) {}

    public function request(Tenant $tenant, AiCreditPackage $package, User $actor): AiCreditPurchase
    {
        if (! BillingSetting::current()->ai_credit_purchases_enabled) {
            throw ValidationException::withMessages(['package' => ['A compra de créditos de IA ainda não está liberada.']]);
        }

        if (! $package->active) {
            throw ValidationException::withMessages(['package' => ['Este pacote não está mais disponível.']]);
        }

        return DB::transaction(function () use ($tenant, $package, $actor): AiCreditPurchase {
            Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->first();

            $open = AiCreditPurchase::query()->forTenant($tenant)->where('status', AiCreditPurchaseStatus::AwaitingPayment->value)->first();

            if ($open !== null) {
                return $open;
            }

            $purchase = new AiCreditPurchase;
            $purchase->forceFill([
                'tenant_id' => $tenant->getKey(),
                'package_id' => $package->getKey(),
                'package_name' => $package->name,
                'credits' => $package->credits,
                'price_cents' => $package->price_cents,
                'status' => AiCreditPurchaseStatus::AwaitingPayment,
                'idempotency_key' => "tenant:{$tenant->getKey()}:".now()->format('YmdHisu').':'.bin2hex(random_bytes(4)),
                'user_id' => $actor->getKey(),
            ])->save();

            $this->auditLogger->record('ai_credits.purchase_requested', $tenant, $purchase, ['credits' => $package->credits, 'price_cents' => $package->price_cents], $actor);

            return $purchase;
        });
    }

    /**
     * Payment confirmed by the RootAdmin. Confirming twice credits once.
     */
    public function confirmPayment(AiCreditPurchase $purchase, User $actor, string $reason): AiCreditPurchase
    {
        return DB::transaction(function () use ($purchase, $actor, $reason): AiCreditPurchase {
            $purchase = $this->locked($purchase);

            if ($purchase->status !== AiCreditPurchaseStatus::AwaitingPayment) {
                return $purchase;
            }

            $purchase->forceFill(['status' => AiCreditPurchaseStatus::Paid, 'paid_at' => now(), 'paid_via' => 'manual', 'note' => mb_substr($reason, 0, 500)])->save();
            $this->credits->creditPurchase($purchase, $actor);
            $this->auditLogger->record('ai_credits.purchase_paid', $purchase->tenant, $purchase, ['reason' => $reason, 'credits' => $purchase->credits], $actor);

            return $purchase;
        });
    }

    public function cancel(AiCreditPurchase $purchase, User $actor, string $reason): AiCreditPurchase
    {
        return DB::transaction(function () use ($purchase, $actor, $reason): AiCreditPurchase {
            $purchase = $this->locked($purchase);

            if ($purchase->status !== AiCreditPurchaseStatus::AwaitingPayment) {
                throw ValidationException::withMessages(['purchase' => ['Só é possível cancelar uma compra aguardando pagamento.']]);
            }

            $purchase->forceFill(['status' => AiCreditPurchaseStatus::Cancelled, 'note' => mb_substr($reason, 0, 500)])->save();
            $this->auditLogger->record('ai_credits.purchase_cancelled', $purchase->tenant, $purchase, ['reason' => $reason], $actor);

            return $purchase;
        });
    }

    /**
     * Refund the unused credits of a paid purchase. The refunded amount is proportional to the credits removed; the
     * money itself is returned outside the platform and this records it.
     */
    public function refund(AiCreditPurchase $purchase, User $actor, string $reason): AiCreditPurchase
    {
        return DB::transaction(function () use ($purchase, $actor, $reason): AiCreditPurchase {
            $purchase = $this->locked($purchase);

            if (! in_array($purchase->status, [AiCreditPurchaseStatus::Paid, AiCreditPurchaseStatus::PartiallyRefunded], true)) {
                throw ValidationException::withMessages(['purchase' => ['Só é possível estornar uma compra paga.']]);
            }

            $refundable = $purchase->credits - $purchase->refunded_credits;
            $removed = $this->credits->debitRefund($purchase, $refundable, "refund:{$purchase->getKey()}:{$purchase->refunded_credits}", $actor, $reason);

            if ($removed === 0) {
                throw ValidationException::withMessages(['purchase' => ['Os créditos desta compra já foram consumidos; não há saldo a estornar.']]);
            }

            $cents = (int) round($purchase->price_cents * $removed / max(1, $purchase->credits));
            $refundedCredits = $purchase->refunded_credits + $removed;

            $purchase->forceFill([
                'refunded_credits' => $refundedCredits,
                'refunded_cents' => min($purchase->price_cents, $purchase->refunded_cents + $cents),
                'status' => $refundedCredits >= $purchase->credits ? AiCreditPurchaseStatus::Refunded : AiCreditPurchaseStatus::PartiallyRefunded,
                'note' => mb_substr($reason, 0, 500),
            ])->save();

            $this->auditLogger->record('ai_credits.purchase_refunded', $purchase->tenant, $purchase, ['reason' => $reason, 'credits' => $removed, 'cents' => $cents], $actor);

            return $purchase;
        });
    }

    private function locked(AiCreditPurchase $purchase): AiCreditPurchase
    {
        return AiCreditPurchase::query()->with('tenant')->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
    }
}
