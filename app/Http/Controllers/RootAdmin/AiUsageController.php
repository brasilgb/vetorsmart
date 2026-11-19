<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\AiCreditPurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\AiCreditAccount;
use App\Models\AiCreditEntry;
use App\Models\AiCreditPurchase;
use App\Models\AssistantSetting;
use App\Models\AssistantUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\PlatformTime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IA e Créditos: real usage of the virtual attendant per company in the current month (messages, provider calls,
 * failures and tokens from assistant_usage), the AI credit balances and consumption, and the credit purchases
 * waiting for confirmation. Read-only; homologation companies are flagged and left out of the totals. No cost is
 * estimated without a configured provider price.
 */
class AiUsageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $monthStart = now()->startOfMonth();
        $homologIds = Tenant::query()->where('is_homolog', true)->pluck('id');

        $usage = AssistantUsage::query()
            ->where('usage_date', '>=', $monthStart->toDateString())
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, SUM(messages) AS messages, SUM(provider_calls) AS provider_calls, SUM(provider_failures) AS provider_failures, SUM(tokens) AS tokens')
            ->get()
            ->keyBy('tenant_id');

        $consumed = AiCreditEntry::query()
            ->where('kind', 'consumption')
            ->where('created_at', '>=', $monthStart)
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, SUM(credits) AS credits, SUM(uncovered_credits) AS uncovered')
            ->get()
            ->keyBy('tenant_id');

        $accounts = AiCreditAccount::query()->get()->keyBy('tenant_id');
        $enabled = AssistantSetting::query()->where('enabled', true)->pluck('tenant_id')->flip();
        $subscribed = Subscription::query()->pluck('tenant_id')->flip();

        $tenantIds = $usage->keys()->merge($consumed->keys())->merge($accounts->keys())->merge($enabled->keys())->unique();

        $rows = Tenant::query()->whereIn('id', $tenantIds)->orderBy('name')->get(['id', 'name', 'is_homolog'])
            ->map(function (Tenant $tenant) use ($usage, $consumed, $accounts, $enabled, $subscribed): array {
                $month = $usage->get($tenant->id);
                $credits = $consumed->get($tenant->id);
                $account = $accounts->get($tenant->id);

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'is_homolog' => $tenant->isHomolog(),
                    'assistant_enabled' => $enabled->has($tenant->id),
                    'messages' => (int) ($month->messages ?? 0),
                    'provider_calls' => (int) ($month->provider_calls ?? 0),
                    'provider_failures' => (int) ($month->provider_failures ?? 0),
                    'tokens' => (int) ($month->tokens ?? 0),
                    'credits_consumed' => (int) ($credits->credits ?? 0),
                    'credits_uncovered' => (int) ($credits->uncovered ?? 0),
                    'included_balance' => $account === null ? null : max(0, $account->included_balance),
                    'purchased_balance' => $account === null ? null : max(0, $account->purchased_balance),
                    'has_subscription' => $subscribed->has($tenant->id),
                ];
            })
            ->values();

        $real = $rows->where('is_homolog', false);

        return Inertia::render('rootadmin/ai/index', [
            'period' => $monthStart->format('m/Y'),
            'provider' => (string) config('assistant.provider'),
            'franchise' => Plan::main()->ai_monthly_credits,
            'costConfigured' => config('assistant.cost_per_million_tokens_cents') !== [],
            'totals' => [
                'assistant_enabled' => $real->where('assistant_enabled', true)->count(),
                'messages' => $real->sum('messages'),
                'provider_calls' => $real->sum('provider_calls'),
                'provider_failures' => $real->sum('provider_failures'),
                'tokens' => $real->sum('tokens'),
                'credits_consumed' => $real->sum('credits_consumed'),
            ],
            'rows' => $rows->all(),
            'purchases' => AiCreditPurchase::query()->with('tenant:id,name')
                ->where('status', AiCreditPurchaseStatus::AwaitingPayment->value)
                ->whereNotIn('tenant_id', $homologIds)
                ->latest('id')->limit(20)->get()
                ->map(fn (AiCreditPurchase $purchase): array => [
                    'id' => $purchase->id,
                    'tenant' => $purchase->tenant->name,
                    'subscription_id' => Subscription::query()->where('tenant_id', $purchase->tenant_id)->value('id'),
                    'package' => $purchase->package_name,
                    'credits' => $purchase->credits,
                    'price' => 'R$ '.number_format($purchase->price_cents / 100, 2, ',', '.'),
                    'at' => PlatformTime::dateTime($purchase->created_at),
                ])->all(),
        ]);
    }
}
