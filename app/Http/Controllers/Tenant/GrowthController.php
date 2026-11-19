<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Growth\GrowthMetrics;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meu Crescimento: real figures of the authenticated company only (the tenant always comes from TenantContext):
 * requests, appointments, completed services, conversion (from five requests), services active and on the site, the
 * last six months and the comparison with the previous month. No visits, traffic, revenue or search ranking.
 */
class GrowthController extends Controller
{
    public function __invoke(TenantContext $context, GrowthMetrics $metrics): Response
    {
        $tenant = $context->requireTenant();
        Gate::authorize('viewGrowth', $tenant);

        $ids = collect([$tenant->id]);
        $current = CarbonImmutable::now($tenant->timezone)->startOfMonth();
        $previous = $current->subMonth();

        $month = fn (CarbonImmutable $start): array => $this->figures($metrics->activity($ids, $start->utc(), $start->endOfMonth()->utc()), $tenant->id, $metrics);
        $now = $month($current);
        $before = $month($previous);
        ['websites' => $websites, 'versions' => $versions] = $metrics->sites($ids);
        $site = $metrics->site($tenant, $websites->get($tenant->id), $versions);
        $evolution = $metrics->evolution($tenant->id, collect());

        return Inertia::render('tenant/growth/index', [
            'period' => $current->format('m/Y'),
            'previousPeriod' => $previous->format('m/Y'),
            'current' => $now,
            'previous' => $before,
            'comparison' => $this->comparison($now, $before),
            'services' => ['active' => $now['active_services'], 'on_site' => $site['public_services'], 'site' => $site['site']],
            'seo' => $site['seo'],
            'evolution' => array_map(fn (array $row): array => [
                'month' => $row['month'],
                'requests' => $row['requests'],
                'appointments_done' => $row['appointments_done'],
                'completed' => $row['completed'],
            ], $evolution),
            'hasActivity' => array_sum(array_map(fn (array $row): int => $row['requests'] + $row['appointments_done'] + $row['completed'], $evolution)) > 0,
            'minRequestsForConversion' => GrowthMetrics::MIN_REQUESTS_FOR_CONVERSION,
            // Requests of the month by origin (Regional Portal included), followed until appointment and completion.
            'origins' => $metrics->byOrigin($tenant->id, $current->utc(), $current->endOfMonth()->utc()),
            'previousOrigins' => $metrics->byOrigin($tenant->id, $previous->utc(), $previous->endOfMonth()->utc()),
        ]);
    }

    /**
     * @param  array<string, Collection<int|string, int>>  $activity
     * @return array{requests: int, appointments_done: int, completed: int, cancelled: int, conversion: int|null, active_services: int}
     */
    private function figures(array $activity, int $tenantId, GrowthMetrics $metrics): array
    {
        $requests = (int) ($activity['requests'][$tenantId] ?? 0);
        $completed = (int) ($activity['completed'][$tenantId] ?? 0);

        return [
            'requests' => $requests,
            'appointments_done' => (int) ($activity['appointments_done'][$tenantId] ?? 0),
            'completed' => $completed,
            'cancelled' => (int) ($activity['cancelled'][$tenantId] ?? 0),
            'conversion' => $metrics->conversion($requests, $completed),
            'active_services' => (int) ($activity['active_services'][$tenantId] ?? 0),
        ];
    }

    /**
     * Change against the previous month, only where the previous month has data (otherwise there is nothing to compare).
     *
     * @param  array{requests: int, appointments_done: int, completed: int, cancelled: int, conversion: int|null, active_services: int}  $now
     * @param  array{requests: int, appointments_done: int, completed: int, cancelled: int, conversion: int|null, active_services: int}  $before
     * @return array<string, int|null>
     */
    private function comparison(array $now, array $before): array
    {
        $delta = fn (int $current, int $previous): ?int => $previous > 0 ? (int) round(($current - $previous) * 100 / $previous) : null;

        return [
            'requests' => $delta($now['requests'], $before['requests']),
            'appointments_done' => $delta($now['appointments_done'], $before['appointments_done']),
            'completed' => $delta($now['completed'], $before['completed']),
            'cancelled' => $delta($now['cancelled'], $before['cancelled']),
            'conversion' => $now['conversion'] !== null && $before['conversion'] !== null ? $now['conversion'] - $before['conversion'] : null,
        ];
    }
}
