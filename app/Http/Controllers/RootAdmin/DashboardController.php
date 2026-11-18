<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\AppointmentStatus;
use App\Enums\ChargeStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WebsiteStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AssistantUsage;
use App\Models\BillingCharge;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantWebsite;
use App\Models\User;
use App\Services\HomologPurgeService;
use App\Support\PlatformTime;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Platform indicators computed with grouped queries. Lists are never loaded to count them.
     *
     * Indicators that depend on modules not implemented yet are returned as `pending`, so the
     * console states they are unavailable instead of showing invented numbers.
     *
     * Homologation companies (internal is_homolog mark) and their users stay out of every indicator, so test
     * activity never inflates the commercial numbers. They remain visible in the company list, under their own name.
     */
    public function __invoke(): Response
    {
        $homologIds = Tenant::query()->where('is_homolog', true)->pluck('id')->all();

        $byStatus = Tenant::query()
            ->whereKeyNot($homologIds)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $distribution = DB::table('tenants')
            ->leftJoin('segments', 'segments.id', '=', 'tenants.segment_id')
            ->whereNotIn('tenants.id', $homologIds)
            ->selectRaw("COALESCE(segments.name, 'Sem segmento') as segment_name, COUNT(tenants.id) as total")
            ->groupBy('segment_name')
            ->orderByDesc('total')
            ->get();

        $recent = Tenant::query()
            ->with('segment:id,name')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'status', 'is_homolog', 'segment_id', 'created_at'])
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'is_homolog' => $tenant->isHomolog(),
                'segment' => $tenant->segment?->name,
                'status' => $tenant->status->value,
                'created_at' => PlatformTime::date($tenant->created_at),
            ]);

        $monthStart = now()->startOfMonth()->toDateString();

        $aiUsage = AssistantUsage::query()
            ->where('usage_date', '>=', $monthStart)
            ->whereNotIn('tenant_id', $homologIds)
            ->selectRaw('COALESCE(SUM(messages), 0) as messages, COALESCE(SUM(provider_calls), 0) as provider_calls, COALESCE(SUM(provider_failures), 0) as provider_failures, COALESCE(SUM(tokens), 0) as tokens')
            ->first();

        return Inertia::render('rootadmin/dashboard', [
            'totals' => [
                'tenants' => (int) $byStatus->sum(),
                'active' => (int) ($byStatus['active'] ?? 0),
                'suspended' => (int) ($byStatus['suspended'] ?? 0),
                'users' => User::query()->where('email', 'not like', HomologPurgeService::EMAIL_PATTERN)->count(),
            ],
            'sites' => [
                'published' => TenantWebsite::query()
                    ->where('status', WebsiteStatus::Published)
                    ->whereNotNull('published_version_id')
                    ->whereNotIn('tenant_id', $homologIds)
                    ->count(),
            ],
            'appointments' => [
                'upcoming' => Appointment::query()
                    ->where('status', AppointmentStatus::Scheduled)
                    ->where('starts_at', '>=', now())
                    ->whereNotIn('tenant_id', $homologIds)
                    ->count(),
            ],
            'ai' => [
                'period' => now()->format('m/Y'),
                'messages' => (int) $aiUsage->messages,
                'provider_calls' => (int) $aiUsage->provider_calls,
                'provider_failures' => (int) $aiUsage->provider_failures,
                'tokens' => (int) $aiUsage->tokens,
            ],
            'billing' => $this->billing($homologIds),
            'pending' => [
                ['label' => 'Custo estimado de IA', 'reason' => 'Não há tabela de preços do provedor cadastrada.'],
                ['label' => 'Incidentes operacionais', 'reason' => 'Registro de incidentes ainda não está implementado.'],
            ],
            'distribution' => $distribution->map(fn ($row): array => [
                'name' => $row->segment_name,
                'total' => (int) $row->total,
            ])->values(),
            'recent' => $recent,
        ]);
    }

    /**
     * Real billing figures, without homologation companies. The recurring revenue is the monthly equivalent of the
     * active subscriptions (contracted price less discount); companies on trial add nothing.
     *
     * @param  array<int, mixed>  $homologIds
     * @return array{trialing: int, active: int, past_due: int, suspended: int, open_charges: int, mrr: string}
     */
    private function billing(array $homologIds): array
    {
        $subscriptions = Subscription::query()->with('plan')->whereNotIn('tenant_id', $homologIds)->get();
        $byStatus = $subscriptions->countBy(fn (Subscription $subscription): string => $subscription->status->value);

        $mrrCents = $subscriptions
            ->filter(fn (Subscription $subscription): bool => $subscription->status === SubscriptionStatus::Active)
            ->sum(fn (Subscription $subscription): float => ($subscription->periodAmountCents() ?? 0) / max(1, $subscription->plan->interval_months));

        return [
            'trialing' => (int) ($byStatus[SubscriptionStatus::Trialing->value] ?? 0),
            'active' => (int) ($byStatus[SubscriptionStatus::Active->value] ?? 0),
            'past_due' => (int) ($byStatus[SubscriptionStatus::PastDue->value] ?? 0),
            'suspended' => (int) ($byStatus[SubscriptionStatus::Suspended->value] ?? 0),
            'open_charges' => BillingCharge::query()->whereIn('status', [ChargeStatus::Pending->value, ChargeStatus::Waiting->value])->whereNotIn('tenant_id', $homologIds)->count(),
            'mrr' => 'R$ '.number_format($mrrCents / 100, 2, ',', '.'),
        ];
    }
}
