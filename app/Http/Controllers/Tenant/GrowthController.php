<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\MembershipStatus;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Services\Customers\CustomerPortfolio;
use App\Services\Growth\GrowthMetrics;
use App\Services\Growth\GrowthRecommendations;
use App\Services\Tenancy\TenantContext;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meu Crescimento: commercial results of the authenticated company only (the tenant always comes from TenantContext).
 *
 * - Operational: requests received, converted (received in the period and completed), conversion rate (converted ÷
 *   received, from five requests), services completed and requests closed without the service, with their reasons;
 *   the funnel of the requests received (cohort) apart from the stock of today; channel and commercial origin
 *   (discovery) apart; customer relationship from CustomerPortfolio (recurrence by completed services).
 * - Financial (declared by the company, not proof of payment): revenue of the services completed in the period, the
 *   average ticket of the services with an amount, and how many have none. Unknown amounts never count as zero.
 * - What to do now: deterministic recommendations with links to the records behind them.
 *
 * Filters: period (months in the company's time zone), origin and responsible; the previous period of the same length
 * is the comparison base. Every figure links to the request list with the same filters. Amounts are integer cents,
 * formatted on the server.
 */
class GrowthController extends Controller
{
    /**
     * Period presets. "days": the last N days counting today; "months": calendar months counting the current one
     * (0 = the previous month alone). The comparison base is always the period of the same length just before.
     *
     * @var array<string, array{label: string, months?: int, days?: int}>
     */
    private const array PERIODS = [
        '30-dias' => ['label' => 'Últimos 30 dias', 'days' => 30],
        '60-dias' => ['label' => 'Últimos 60 dias', 'days' => 60],
        '90-dias' => ['label' => 'Últimos 90 dias', 'days' => 90],
        'mes' => ['label' => 'Este mês', 'months' => 1],
        'mes-anterior' => ['label' => 'Mês anterior', 'months' => 0],
        '3-meses' => ['label' => 'Últimos 3 meses', 'months' => 3],
        '6-meses' => ['label' => 'Últimos 6 meses', 'months' => 6],
        '12-meses' => ['label' => 'Últimos 12 meses', 'months' => 12],
    ];

    public function __invoke(Request $request, TenantContext $context, GrowthMetrics $metrics, GrowthRecommendations $recommendations, CustomerPortfolio $portfolio): Response
    {
        $tenant = $context->requireTenant();
        Gate::authorize('viewGrowth', $tenant);

        $period = array_key_exists($request->string('periodo')->value(), self::PERIODS) ? $request->string('periodo')->value() : 'mes';
        $origin = RequestOrigin::tryFrom($request->string('origem')->value());
        $assignees = $this->assignees($tenant->id);
        $responsibleInput = $request->string('responsavel')->value();
        $responsible = match (true) {
            $responsibleInput === 'nenhum' => 'none',
            ctype_digit($responsibleInput) && in_array((int) $responsibleInput, array_column($assignees, 'id'), true) => (int) $responsibleInput,
            default => null,
        };

        [$from, $to, $previousFrom, $previousTo] = $this->window($period, CarbonImmutable::now($tenant->timezone));

        $now = $this->figures($metrics->tenantFigures($tenant->id, $from->utc(), $to->utc(), $origin, $responsible), $metrics);
        $before = $this->figures($metrics->tenantFigures($tenant->id, $previousFrom->utc(), $previousTo->utc(), $origin, $responsible), $metrics);

        $ids = collect([$tenant->id]);
        $activity = $metrics->activity($ids, $from->utc(), $to->utc());
        ['websites' => $websites, 'versions' => $versions] = $metrics->sites($ids);
        $site = $metrics->site($tenant, $websites->get($tenant->id), $versions);
        $evolution = $metrics->evolution($tenant->id, collect(), $tenant->timezone);

        // The request list with the same origin and responsible, for the records behind each figure.
        $responsibleFilter = $responsible === 'none' ? 'nenhum' : ($responsible === null ? '' : (string) $responsible);
        $list = fn (array $query): string => route('tenant.requests.index', array_filter([
            'origem' => $origin->value ?? '',
            'responsavel' => $responsibleFilter,
            ...$query,
        ], fn (mixed $value): bool => $value !== '' && $value !== null), false);
        $received = ['de' => $from->toDateString(), 'ate' => $to->toDateString()];
        $closed = [...$received, 'data' => 'encerramento'];

        $byDays = isset(self::PERIODS[$period]['days']);
        $commercial = $metrics->byCommercialOrigin($tenant->id, $from->utc(), $to->utc(), $origin, $responsible);
        // Days analysed, for the "without recent service" segment (same rule as the customer portfolio).
        $windowDays = max(1, (int) $from->diffInDays(CarbonImmutable::now($tenant->timezone)) + 1);

        return Inertia::render('tenant/growth/index', [
            'period' => $this->label($from, $to, $byDays),
            'previousPeriod' => $this->label($previousFrom, $previousTo, $byDays),
            // Funnel of the requests received in the period (conversion), and what is in each stage now (stock).
            'funnel' => $metrics->funnel($tenant->id, $from->utc(), $to->utc(), $origin, $responsible),
            'stock' => $metrics->stock($tenant->id, $origin, $responsible),
            // Commercial origin (how the customer found the company) and entry channel, in separate views (GROWTH-03).
            'commercialOrigins' => array_map(fn (array $row): array => [...$row, 'revenue' => $row['revenue_known'] > 0 ? Money::format($row['revenue_cents']) : null], $commercial['origins']),
            'campaigns' => array_map(fn (array $row): array => [...$row, 'revenue' => $row['revenue_known'] > 0 ? Money::format($row['revenue_cents']) : null], $commercial['campaigns']),
            'channels' => array_map(fn (array $row): array => [...$row, 'revenue' => $row['revenue_known'] > 0 ? Money::format($row['revenue_cents']) : null], $metrics->byChannel($tenant->id, $from->utc(), $to->utc(), $origin, $responsible)),
            // Customer relationship (CustomerPortfolio, the same rules as the portfolio). It covers the whole company,
            // so the origin and responsible filters do not apply here.
            'relationship' => [...$portfolio->growth($tenant->id, $from->utc(), $to->utc(), $windowDays), 'days' => $windowDays],
            'customerLinks' => [
                'recurring' => route('tenant.customers.index', ['relacao' => 'recorrentes'], false),
                'without_recent' => route('tenant.customers.index', ['relacao' => 'sem-atendimento', 'dias' => in_array($windowDays, CustomerPortfolio::PERIODS, true) ? $windowDays : 90], false),
                'new' => route('tenant.customers.index', ['relacao' => 'novos', 'dias' => in_array($windowDays, CustomerPortfolio::PERIODS, true) ? $windowDays : 90], false),
            ],
            'filters' => [
                'periodo' => $period,
                'origem' => $origin->value ?? '',
                'responsavel' => $responsibleFilter,
            ],
            'options' => [
                'periods' => array_map(fn (string $key, array $preset): array => ['value' => $key, 'label' => $preset['label']], array_keys(self::PERIODS), self::PERIODS),
                // The channel filter keeps the stored values; the portal value is the site form opened from the portal.
                'origins' => array_map(fn (RequestOrigin $item): array => ['value' => $item->value, 'label' => $item === RequestOrigin::Portal ? 'Site — formulário (vindo do portal)' : $item->channel()['label']], RequestOrigin::cases()),
                'assignees' => $assignees,
            ],
            'current' => $now,
            'previous' => $before,
            'comparison' => $this->comparison($now, $before),
            'links' => [
                'received' => $list($received),
                'converted' => $list([...$received, 'status' => RequestStatus::Completed->value]),
                'completed' => $list([...$closed, 'status' => RequestStatus::Completed->value]),
                'cancelled' => $list([...$closed, 'status' => 'sem-servico']),
                'without_amount' => $list([...$closed, 'status' => RequestStatus::Completed->value, 'sem_valor' => 1]),
            ],
            'recommendations' => $recommendations->for($tenant, $now, $before),
            'closingReasons' => $metrics->closingReasons($tenant->id, $from->utc(), $to->utc(), $origin, $responsible),
            'services' => ['active' => (int) ($activity['active_services'][$tenant->id] ?? 0), 'on_site' => $site['public_services'], 'site' => $site['site']],
            'seo' => $site['seo'],
            'evolution' => array_map(fn (array $row): array => [
                'month' => $row['month'],
                'requests' => $row['requests'],
                'appointments_done' => $row['appointments_done'],
                'completed' => $row['completed'],
                'revenue' => $row['revenue_cents'] > 0 ? Money::format($row['revenue_cents']) : null,
                'revenue_cents' => $row['revenue_cents'],
                'revenue_unknown' => $row['revenue_unknown'],
            ], $evolution),
            'hasActivity' => array_sum(array_map(fn (array $row): int => $row['requests'] + $row['appointments_done'] + $row['completed'], $evolution)) > 0,
            'minRequestsForConversion' => GrowthMetrics::MIN_REQUESTS_FOR_CONVERSION,
            // Requests of the period by origin, followed until appointment, completion or closing without the service.
            'origins' => array_map(fn (array $row): array => [...$row, 'href' => $list([...$received, 'origem' => $row['origin']])], array_values(array_filter(
                $metrics->byOrigin($tenant->id, $from->utc(), $to->utc(), $responsible),
                fn (array $row): bool => $origin === null || $row['origin'] === $origin->value,
            ))),
            'previousOrigins' => $metrics->byOrigin($tenant->id, $previousFrom->utc(), $previousTo->utc(), $responsible),
            'revenueByOrigin' => array_map(fn (array $row): array => [
                ...$row,
                'revenue' => $row['revenue_known'] > 0 ? Money::format($row['revenue_cents']) : null,
                'ticket' => ($ticket = $metrics->averageTicket($row['revenue_cents'], $row['revenue_known'])) === null ? null : Money::format($ticket),
            ], array_values(array_filter($metrics->revenueByOrigin($tenant->id, $from->utc(), $to->utc(), $responsible), fn (array $row): bool => $origin === null || $row['origin'] === $origin->value))),
        ]);
    }

    /**
     * Start and end of the chosen period and of the previous one of the same length, in the company's time zone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable}
     */
    private function window(string $period, CarbonImmutable $now): array
    {
        if (isset(self::PERIODS[$period]['days'])) {
            $days = self::PERIODS[$period]['days'];
            $start = $now->startOfDay()->subDays($days - 1);

            return [$start, $now->endOfDay(), $start->subDays($days), $start->subSecond()];
        }

        $months = self::PERIODS[$period]['months'] ?? 1;
        $end = $months === 0 ? $now->startOfMonth()->subMonth()->endOfMonth() : $now->endOfMonth();
        $length = max(1, $months);
        $start = $end->startOfMonth()->subMonths($length - 1);

        return [$start, $end, $start->subMonths($length), $start->subSecond()];
    }

    private function label(CarbonImmutable $from, CarbonImmutable $to, bool $days = false): string
    {
        if ($days) {
            return $from->format('d/m/Y').' a '.$to->format('d/m/Y');
        }

        return $from->format('m/Y') === $to->format('m/Y') ? $from->format('m/Y') : $from->format('m/Y').' a '.$to->format('m/Y');
    }

    /**
     * @param  array{requests: int, converted: int, completed: int, cancelled: int, appointments_done: int, revenue_cents: int, revenue_known: int, revenue_unknown: int}  $figures
     * @return array{requests: int, converted: int, appointments_done: int, completed: int, cancelled: int, conversion: int|null, revenue: string|null, revenue_cents: int, revenue_known: int, revenue_unknown: int, ticket: string|null, ticket_cents: int|null}
     */
    private function figures(array $figures, GrowthMetrics $metrics): array
    {
        $ticket = $metrics->averageTicket($figures['revenue_cents'], $figures['revenue_known']);

        return [
            'requests' => $figures['requests'],
            'converted' => $figures['converted'],
            'appointments_done' => $figures['appointments_done'],
            'completed' => $figures['completed'],
            'cancelled' => $figures['cancelled'],
            'conversion' => $metrics->conversion($figures['requests'], $figures['converted']),
            'revenue' => $figures['revenue_known'] > 0 ? Money::format($figures['revenue_cents']) : null,
            'revenue_cents' => $figures['revenue_cents'],
            'revenue_known' => $figures['revenue_known'],
            'revenue_unknown' => $figures['revenue_unknown'],
            'ticket' => $ticket === null ? null : Money::format($ticket),
            'ticket_cents' => $ticket,
        ];
    }

    /**
     * Change against the previous period, only where the previous one has data (otherwise there is nothing to compare).
     *
     * @param  array{requests: int, converted: int, appointments_done: int, completed: int, cancelled: int, conversion: int|null, revenue: string|null, revenue_cents: int, revenue_known: int, revenue_unknown: int, ticket: string|null, ticket_cents: int|null}  $now
     * @param  array{requests: int, converted: int, appointments_done: int, completed: int, cancelled: int, conversion: int|null, revenue: string|null, revenue_cents: int, revenue_known: int, revenue_unknown: int, ticket: string|null, ticket_cents: int|null}  $before
     * @return array<string, int|null>
     */
    private function comparison(array $now, array $before): array
    {
        // A percentage of change for display; amounts themselves are never computed in floating point.
        $delta = fn (int $current, int $previous): ?int => $previous > 0 ? (int) round(($current - $previous) * 100 / $previous) : null;

        return [
            'requests' => $delta($now['requests'], $before['requests']),
            'converted' => $delta($now['converted'], $before['converted']),
            'appointments_done' => $delta($now['appointments_done'], $before['appointments_done']),
            'completed' => $delta($now['completed'], $before['completed']),
            'cancelled' => $delta($now['cancelled'], $before['cancelled']),
            'conversion' => $now['conversion'] !== null && $before['conversion'] !== null ? $now['conversion'] - $before['conversion'] : null,
            // Compared only when both periods have known amounts.
            'revenue' => $now['revenue_known'] > 0 && $before['revenue_known'] > 0 ? $delta($now['revenue_cents'], $before['revenue_cents']) : null,
            'ticket' => $now['ticket_cents'] !== null && $before['ticket_cents'] !== null ? $delta($now['ticket_cents'], $before['ticket_cents']) : null,
        ];
    }

    /**
     * Active members who can be responsible for requests, for the responsible filter.
     *
     * @return list<array{id: int, name: string}>
     */
    private function assignees(int $tenantId): array
    {
        return array_values(TenantUser::query()
            ->forTenant($tenantId)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::Active->value))
            ->with('user:id,name')
            ->get()
            ->map(fn (TenantUser $member): array => ['id' => (int) $member->user_id, 'name' => (string) $member->user?->name])
            ->sortBy('name')
            ->values()
            ->all());
    }
}
