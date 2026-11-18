<?php

namespace App\Services\Billing;

use App\Models\AiCreditAccount;
use App\Models\AiCreditEntry;
use App\Models\AiCreditPurchase;
use App\Models\BillingSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OperationalNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * AI credits of a company, in two buckets:
 *
 * - included: the monthly franchise of the plan, renewed at the start of every month in the company's timezone
 *   (also for semiannual and annual contracts); what is left of the previous month expires;
 * - purchased: credits bought in packages; they never expire.
 *
 * Paid AI operations consume the included bucket first, then the purchased one. Every change of a balance happens
 * under a row lock of the account together with an entry in the ledger, whose unique reference makes each grant,
 * consumption, purchase and refund apply once. Credits are enforced only when the plan defines a franchise and the
 * company has a subscription; otherwise consumption is only recorded. Only paid AI operations are ever blocked.
 */
class AiCreditService
{
    public function __construct(private OperationalNotifier $notifier) {}

    /**
     * Whether the credits are enforced for the company: it has a subscription and the plan defines a franchise.
     */
    public function enforced(Tenant $tenant): bool
    {
        return $this->franchise($tenant) !== null;
    }

    /**
     * Whether a paid AI operation may run now.
     */
    public function canSpend(Tenant $tenant): bool
    {
        if (! $this->enforced($tenant)) {
            return true;
        }

        return $this->account($tenant)->totalBalance() > 0;
    }

    /**
     * The account with the franchise of the current month already granted.
     */
    public function account(Tenant $tenant): AiCreditAccount
    {
        return DB::transaction(fn (): AiCreditAccount => $this->lockedAccount($tenant));
    }

    /**
     * Consumption of a paid AI operation. The reference identifies the operation: recording it again returns the first
     * entry and debits nothing. Tokens become credits by the configured rate (rounded up). When the balance does not
     * cover it, the balance goes to zero and the rest is kept as uncovered for reconciliation.
     */
    public function recordConsumption(Tenant $tenant, string $reference, string $operation, string $model, int $tokens): AiCreditEntry
    {
        $reference = 'consumption:'.$reference;
        $tokens = max(0, $tokens);
        $credits = $tokens === 0 ? 0 : (int) ceil($tokens / max(1, BillingSetting::current()->ai_tokens_per_credit));
        $enforced = $this->enforced($tenant);

        [$entry, $account] = DB::transaction(function () use ($tenant, $reference, $operation, $model, $tokens, $credits, $enforced): array {
            $account = $this->lockedAccount($tenant);
            $existing = AiCreditEntry::query()->where('reference', $reference)->first();

            if ($existing !== null) {
                return [$existing, null];
            }

            $fromIncluded = $fromPurchased = 0;

            if ($enforced) {
                $fromIncluded = min($credits, max(0, $account->included_balance));
                $fromPurchased = min($credits - $fromIncluded, max(0, $account->purchased_balance));
            }

            $account->forceFill([
                'included_balance' => $account->included_balance - $fromIncluded,
                'purchased_balance' => $account->purchased_balance - $fromPurchased,
            ])->save();

            $entry = $this->entry($tenant, 'consumption', $reference, -$fromIncluded, -$fromPurchased, [
                'credits' => $credits,
                'uncovered_credits' => $enforced ? $credits - $fromIncluded - $fromPurchased : 0,
                'operation' => mb_substr($operation, 0, 60),
                'model' => mb_substr($model, 0, 60),
                'tokens' => $tokens,
                'estimated_cost_micros' => $this->estimatedCostMicros($model, $tokens),
            ]);

            return [$entry, $enforced ? $account : null];
        });

        if ($account !== null) {
            $this->alertLowBalance($tenant, $account);
        }

        return $entry;
    }

    /**
     * Credit a paid purchase to the purchased bucket, once.
     */
    public function creditPurchase(AiCreditPurchase $purchase, ?User $actor = null): void
    {
        DB::transaction(function () use ($purchase, $actor): void {
            $account = $this->lockedAccount($purchase->tenant);

            if (AiCreditEntry::query()->where('reference', "purchase:{$purchase->getKey()}")->exists()) {
                return;
            }

            $account->forceFill(['purchased_balance' => $account->purchased_balance + $purchase->credits])->save();
            $this->entry($purchase->tenant, 'purchase', "purchase:{$purchase->getKey()}", 0, $purchase->credits, [
                'credits' => $purchase->credits,
                'purchase_id' => $purchase->getKey(),
                'note' => $purchase->package_name,
                'user_id' => $actor?->getKey(),
            ]);
        });
    }

    /**
     * Remove refunded credits from the purchased bucket. Only credits still in the balance can leave it: consumed
     * credits are never refunded. Returns the credits actually removed.
     */
    public function debitRefund(AiCreditPurchase $purchase, int $credits, string $reference, User $actor, string $reason): int
    {
        return DB::transaction(function () use ($purchase, $credits, $reference, $actor, $reason): int {
            $account = $this->lockedAccount($purchase->tenant);
            $removed = min($credits, max(0, $account->purchased_balance));

            if ($removed === 0) {
                return 0;
            }

            $account->forceFill(['purchased_balance' => $account->purchased_balance - $removed])->save();
            $this->entry($purchase->tenant, 'refund', $reference, 0, -$removed, [
                'credits' => $removed,
                'purchase_id' => $purchase->getKey(),
                'note' => mb_substr($reason, 0, 500),
                'user_id' => $actor->getKey(),
            ]);

            return $removed;
        });
    }

    /**
     * Compare the balances with the sum of the ledger. Any difference means a change outside this service.
     *
     * @return array{included_balance: int, included_ledger: int, purchased_balance: int, purchased_ledger: int, consistent: bool, uncovered_credits: int}
     */
    public function reconcile(Tenant $tenant): array
    {
        $account = AiCreditAccount::query()->forTenant($tenant)->first();
        $sums = AiCreditEntry::query()->forTenant($tenant)
            ->selectRaw('COALESCE(SUM(included_delta), 0) AS included, COALESCE(SUM(purchased_delta), 0) AS purchased, COALESCE(SUM(uncovered_credits), 0) AS uncovered')
            ->first();

        $included = (int) ($account->included_balance ?? 0);
        $purchased = (int) ($account->purchased_balance ?? 0);
        $includedLedger = (int) ($sums->included ?? 0);
        $purchasedLedger = (int) ($sums->purchased ?? 0);

        return [
            'included_balance' => $included,
            'included_ledger' => $includedLedger,
            'purchased_balance' => $purchased,
            'purchased_ledger' => $purchasedLedger,
            'consistent' => $included === $includedLedger && $purchased === $purchasedLedger,
            'uncovered_credits' => (int) ($sums->uncovered ?? 0),
        ];
    }

    /**
     * Monthly franchise of the company's plan, or null when credits are not enforced.
     */
    public function franchise(Tenant $tenant): ?int
    {
        $subscription = Subscription::query()->forTenant($tenant)->with('plan')->first();

        return $subscription?->plan->ai_monthly_credits;
    }

    public function currentPeriod(Tenant $tenant): string
    {
        return CarbonImmutable::now($tenant->timezone)->format('Y-m');
    }

    /**
     * Lock the account (creating it on first use) and grant the franchise of the current month when it was not granted
     * yet: the leftover of the previous month expires and the bucket restarts at the franchise. Must run inside a
     * transaction.
     */
    private function lockedAccount(Tenant $tenant): AiCreditAccount
    {
        AiCreditAccount::query()->insertOrIgnore(['tenant_id' => $tenant->getKey(), 'created_at' => now(), 'updated_at' => now()]);

        $account = AiCreditAccount::query()->forTenant($tenant)->lockForUpdate()->firstOrFail();
        $franchise = $this->franchise($tenant);
        $period = $this->currentPeriod($tenant);

        if ($franchise === null || $account->included_period === $period) {
            return $account;
        }

        if ($account->included_balance !== 0) {
            $this->entry($tenant, 'included_expired', "expired:{$tenant->getKey()}:{$period}", -$account->included_balance, 0, [
                'credits' => abs($account->included_balance),
                'note' => 'Saldo da franquia anterior: '.$account->included_balance.' crédito(s).',
            ]);
        }

        $this->entry($tenant, 'monthly_grant', "grant:{$tenant->getKey()}:{$period}", $franchise, 0, ['credits' => $franchise, 'note' => "Franquia de {$period}."]);
        $account->forceFill(['included_balance' => $franchise, 'included_period' => $period])->save();

        return $account;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(Tenant $tenant, string $kind, string $reference, int $includedDelta, int $purchasedDelta, array $attributes = []): AiCreditEntry
    {
        $entry = new AiCreditEntry;
        $entry->forceFill([
            ...$attributes,
            'tenant_id' => $tenant->getKey(),
            'kind' => $kind,
            'reference' => mb_substr($reference, 0, 150),
            'included_delta' => $includedDelta,
            'purchased_delta' => $purchasedDelta,
        ])->save();

        return $entry;
    }

    /**
     * Warn the owners once per month when the balance falls to the configured share of the franchise, and once when
     * it runs out.
     */
    private function alertLowBalance(Tenant $tenant, AiCreditAccount $account): void
    {
        $franchise = $this->franchise($tenant) ?? 0;
        $period = $this->currentPeriod($tenant);
        $balance = $account->totalBalance();

        if ($balance <= 0 && $account->depleted_alerted_period !== $period) {
            $account->forceFill(['depleted_alerted_period' => $period])->save();
            $this->notifier->billingNotice($tenant, "ai-depleted:{$tenant->getKey()}:{$period}", 'Créditos de IA esgotados', 'As respostas automáticas que usam IA ficam pausadas até a renovação da franquia ou a compra de créditos. O atendimento convencional continua normalmente.');

            return;
        }

        $threshold = (int) floor($franchise * BillingSetting::current()->ai_low_balance_percent / 100);

        if ($balance > 0 && $balance <= $threshold && $account->low_balance_alerted_period !== $period) {
            $account->forceFill(['low_balance_alerted_period' => $period])->save();
            $this->notifier->billingNotice($tenant, "ai-low:{$tenant->getKey()}:{$period}", 'Saldo de créditos de IA baixo', "Restam {$balance} crédito(s) de IA neste mês. Acompanhe o consumo em Minha Assinatura.");
        }
    }

    /**
     * Estimated cost in millionths of a real, from the configured price per million tokens of the model (in cents).
     * Null when the model has no configured price.
     */
    private function estimatedCostMicros(string $model, int $tokens): ?int
    {
        $centsPerMillion = config("assistant.cost_per_million_tokens_cents.{$model}");

        return is_numeric($centsPerMillion) ? (int) round($tokens * (float) $centsPerMillion / 100) : null;
    }
}
