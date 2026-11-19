<?php

namespace App\Services\Growth;

use App\Enums\AppointmentStatus;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Enums\TenantStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantWebsite;
use App\Models\WebsiteVersion;
use App\Services\Websites\PublicSiteAddress;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Real growth figures of companies, shared by the RootAdmin (Crescimento e SEO) and the company panel (Meu Crescimento).
 * Everything comes from recorded data: requests, appointments, services and the published site. Nothing is estimated
 * (no visits, traffic, revenue or search ranking). Per-company figures are read with grouped queries.
 */
class GrowthMetrics
{
    /** Minimum requests in a period to show a conversion rate. */
    public const int MIN_REQUESTS_FOR_CONVERSION = 5;

    public function __construct(private PublicSiteAddress $address) {}

    /**
     * Activity of each company in the period.
     *
     * @param  Collection<int, int>  $tenantIds
     * @return array{requests: Collection<int|string, int>, completed: Collection<int|string, int>, cancelled: Collection<int|string, int>, appointments: Collection<int|string, int>, appointments_done: Collection<int|string, int>, active_services: Collection<int|string, int>}
     */
    public function activity(Collection $tenantIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'requests' => $this->countBy(ServiceRequest::query()->whereIn('tenant_id', $tenantIds)->whereBetween('created_at', [$from, $to])),
            'completed' => $this->countBy(ServiceRequest::query()->whereIn('tenant_id', $tenantIds)->where('status', RequestStatus::Completed->value)->whereBetween('closed_at', [$from, $to])),
            // Closed without the service: cancelled or not performed, counted when it was closed (never a conversion).
            'cancelled' => $this->countBy(ServiceRequest::query()->whereIn('tenant_id', $tenantIds)->whereIn('status', [RequestStatus::Cancelled->value, RequestStatus::NotPerformed->value])->whereBetween('closed_at', [$from, $to])),
            'appointments' => $this->countBy(Appointment::query()->whereIn('tenant_id', $tenantIds)->whereBetween('created_at', [$from, $to])),
            'appointments_done' => $this->countBy(Appointment::query()->whereIn('tenant_id', $tenantIds)->where('status', AppointmentStatus::Completed->value)->whereBetween('starts_at', [$from, $to])),
            'active_services' => $this->countBy(Service::query()->whereIn('tenant_id', $tenantIds)->where('active', true)),
        ];
    }

    /**
     * Figures of one company in a period, optionally only for one origin or one responsible ("none" for requests without
     * one). Revenue is the amount the company declared for services completed in the period: the sum of the known
     * amounts (integer cents, summed by the database) and, apart, how many completed services have no amount. Unknown is
     * never added as zero. Cancelled or not performed requests never carry an amount.
     *
     * @return array{requests: int, completed: int, cancelled: int, appointments_done: int, revenue_cents: int, revenue_known: int, revenue_unknown: int}
     */
    public function tenantFigures(int $tenantId, CarbonImmutable $from, CarbonImmutable $to, ?RequestOrigin $origin = null, int|string|null $responsible = null): array
    {
        $requests = fn (): Builder => $this->scoped(ServiceRequest::query()->where('tenant_id', $tenantId), $origin, $responsible);

        $closed = $requests()
            ->whereBetween('closed_at', [$from, $to])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [RequestStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS cancelled', [RequestStatus::Cancelled->value, RequestStatus::NotPerformed->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND realized_amount_cents IS NOT NULL THEN realized_amount_cents ELSE 0 END) AS revenue_cents', [RequestStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND realized_amount_cents IS NOT NULL THEN 1 ELSE 0 END) AS revenue_known', [RequestStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND realized_amount_cents IS NULL THEN 1 ELSE 0 END) AS revenue_unknown', [RequestStatus::Completed->value])
            ->toBase()
            ->first();

        $appointmentsDone = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::Completed->value)
            ->whereBetween('starts_at', [$from, $to])
            ->when($origin !== null || $responsible !== null, fn (Builder $query) => $query->whereHas('serviceRequest', fn (Builder $request) => $this->scoped($request, $origin, $responsible)))
            ->count();

        return [
            'requests' => $requests()->whereBetween('created_at', [$from, $to])->count(),
            'completed' => (int) ($closed->completed ?? 0),
            'cancelled' => (int) ($closed->cancelled ?? 0),
            'appointments_done' => $appointmentsDone,
            'revenue_cents' => (int) ($closed->revenue_cents ?? 0),
            'revenue_known' => (int) ($closed->revenue_known ?? 0),
            'revenue_unknown' => (int) ($closed->revenue_unknown ?? 0),
        ];
    }

    /**
     * Declared revenue of the services completed in the period, by origin of the request. Known amounts are summed;
     * services without an amount are counted apart, never as zero.
     *
     * @return list<array{origin: string, label: string, completed: int, revenue_cents: int, revenue_known: int, revenue_unknown: int}>
     */
    public function revenueByOrigin(int $tenantId, CarbonImmutable $from, CarbonImmutable $to, int|string|null $responsible = null): array
    {
        $rows = $this->scoped(ServiceRequest::query()->where('tenant_id', $tenantId), null, $responsible)
            ->where('status', RequestStatus::Completed->value)
            ->whereBetween('closed_at', [$from, $to])
            ->groupBy('origin')
            ->selectRaw('origin, COUNT(*) AS completed')
            ->selectRaw('SUM(COALESCE(realized_amount_cents, 0)) AS revenue_cents')
            ->selectRaw('SUM(CASE WHEN realized_amount_cents IS NOT NULL THEN 1 ELSE 0 END) AS revenue_known')
            ->toBase()
            ->get()
            ->keyBy('origin');

        $figures = [];

        foreach (RequestOrigin::cases() as $origin) {
            $row = $rows->get($origin->value);
            $completed = (int) ($row->completed ?? 0);
            $known = (int) ($row->revenue_known ?? 0);
            $figures[] = [
                'origin' => $origin->value,
                'label' => $origin->label(),
                'completed' => $completed,
                'revenue_cents' => (int) ($row->revenue_cents ?? 0),
                'revenue_known' => $known,
                'revenue_unknown' => $completed - $known,
            ];
        }

        return $figures;
    }

    /**
     * Limit a query of requests to one origin and one responsible (a user id, or "none").
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scoped(Builder $query, ?RequestOrigin $origin, int|string|null $responsible): Builder
    {
        return $query
            ->when($origin !== null, fn (Builder $scoped) => $scoped->where('origin', $origin?->value))
            ->when($responsible === 'none', fn (Builder $scoped) => $scoped->whereNull('assigned_user_id'))
            ->when(is_int($responsible), fn (Builder $scoped) => $scoped->where('assigned_user_id', $responsible));
    }

    /**
     * Requests of one company received in the period, by origin, followed as a cohort: how many of them reached an
     * appointment (at least one was booked, whatever happened to it later) and how many were completed. A booking is
     * never counted as a completed service, a request closed without the service (cancelled or not performed) is counted
     * apart, and nothing here is a click or a visit: only recorded requests.
     *
     * @return list<array{origin: string, label: string, received: int, scheduled: int, completed: int, cancelled: int}>
     */
    public function byOrigin(int $tenantId, CarbonImmutable $from, CarbonImmutable $to, int|string|null $responsible = null): array
    {
        $rows = $this->scoped(ServiceRequest::query()->where('tenant_id', $tenantId), null, $responsible)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('origin')
            ->selectRaw('origin, COUNT(*) AS received')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [RequestStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS cancelled', [RequestStatus::Cancelled->value, RequestStatus::NotPerformed->value])
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM appointments WHERE appointments.tenant_id = service_requests.tenant_id AND appointments.service_request_id = service_requests.id) THEN 1 ELSE 0 END) AS scheduled')
            ->toBase()
            ->get()
            ->keyBy('origin');

        $figures = [];

        foreach (RequestOrigin::cases() as $origin) {
            $row = $rows->get($origin->value);
            $figures[] = [
                'origin' => $origin->value,
                'label' => $origin->label(),
                'received' => (int) ($row->received ?? 0),
                'scheduled' => (int) ($row->scheduled ?? 0),
                'completed' => (int) ($row->completed ?? 0),
                'cancelled' => (int) ($row->cancelled ?? 0),
            ];
        }

        return $figures;
    }

    /**
     * Conversion rate (completed ÷ received, in %) only with enough requests to mean something.
     */
    public function conversion(int $requests, int $completed): ?int
    {
        return $requests >= self::MIN_REQUESTS_FOR_CONVERSION ? (int) round($completed * 100 / $requests) : null;
    }

    /**
     * Site of each company with the content its visitors see (the published snapshot, or the draft when not live).
     *
     * @param  Collection<int, int>  $tenantIds
     * @return array{websites: Collection<int, TenantWebsite>, versions: Collection<int, WebsiteVersion>}
     */
    public function sites(Collection $tenantIds): array
    {
        $websites = TenantWebsite::query()->whereIn('tenant_id', $tenantIds)->get()->keyBy('tenant_id');
        $versions = WebsiteVersion::query()->whereIn('id', $websites->pluck('published_version_id')->filter())->get(['id', 'content'])->keyBy('id');

        return ['websites' => $websites, 'versions' => $versions];
    }

    /**
     * Site state and technical SEO of a company. "indexable" only means the local configuration allows indexing (live
     * site, SEO switch on, company active, not homologation), never that a search engine indexed it.
     *
     * @param  Collection<int, WebsiteVersion>  $versions
     * @return array{site: string, public_services: int, seo: array{title: bool, description: bool, canonical: string|null, indexable: bool, in_sitemap: bool}}
     */
    public function site(Tenant $tenant, ?TenantWebsite $website, Collection $versions): array
    {
        $live = $website?->isLive() === true;
        $content = $live ? ($versions->get($website->published_version_id)->content ?? []) : ($website->draft_content ?? []);
        $seo = (array) ($content['seo'] ?? []);
        $indexable = $live && ! $tenant->isHomolog() && $tenant->status === TenantStatus::Active && (bool) ($seo['indexable'] ?? true);

        return [
            'site' => $website === null ? 'none' : ($live ? 'published' : $website->status->value),
            'public_services' => $live ? count((array) ($content['services'] ?? [])) : 0,
            'seo' => [
                'title' => filled($seo['title'] ?? null),
                'description' => filled($seo['description'] ?? null),
                'canonical' => $live ? $this->address->canonical($tenant) : null,
                'indexable' => $indexable,
                'in_sitemap' => $indexable,
            ],
        ];
    }

    /**
     * Monthly figures of the last six months, for one company or for the platform (homologation excluded). Months follow
     * the given time zone (the company's own calendar), UTC when none is given.
     *
     * @param  Collection<int, int>  $excludedTenantIds
     * @return list<array{month: string, key: string, tenants: int, sites: int, requests: int, appointments: int, appointments_done: int, completed: int, revenue_cents: int, revenue_unknown: int}>
     */
    public function evolution(?int $tenantId, Collection $excludedTenantIds, ?string $timezone = null): array
    {
        $timezone ??= 'UTC';
        $local = fn (mixed $value): CarbonImmutable => CarbonImmutable::parse($value, 'UTC')->setTimezone($timezone);
        $start = CarbonImmutable::now($timezone)->startOfMonth()->subMonths(5);
        $startUtc = $start->utc();
        $scope = fn (Builder $query): Builder => $tenantId === null ? $query->whereNotIn('tenant_id', $excludedTenantIds) : $query->where('tenant_id', $tenantId);
        $monthly = fn (Builder $query, string $column): Collection => $scope($query)->where($column, '>=', $startUtc)->get([$column])
            ->countBy(fn ($row): string => $local($row->{$column})->format('Y-m'));

        $tenants = $tenantId !== null ? collect() : Tenant::query()->where('created_at', '>=', $startUtc)->where('is_homolog', false)->get(['created_at'])
            ->countBy(fn (Tenant $tenant): string => $tenant->created_at?->format('Y-m') ?? '');
        $sites = $monthly(TenantWebsite::query()->whereNotNull('published_at'), 'published_at');
        $requests = $monthly(ServiceRequest::query(), 'created_at');
        $appointments = $monthly(Appointment::query(), 'created_at');
        $done = $monthly(Appointment::query()->where('status', AppointmentStatus::Completed->value), 'starts_at');
        $completed = $monthly(ServiceRequest::query()->where('status', RequestStatus::Completed->value)->whereNotNull('closed_at'), 'closed_at');
        // Declared revenue of a company by month of completion: known amounts summed in cents, unknown counted apart.
        $revenue = collect();
        $unknown = collect();

        if ($tenantId !== null) {
            foreach (ServiceRequest::query()->where('tenant_id', $tenantId)->where('status', RequestStatus::Completed->value)->where('closed_at', '>=', $startUtc)->get(['closed_at', 'realized_amount_cents']) as $row) {
                $month = $local($row->closed_at)->format('Y-m');

                if ($row->realized_amount_cents === null) {
                    $unknown[$month] = (int) ($unknown[$month] ?? 0) + 1;
                } else {
                    $revenue[$month] = (int) ($revenue[$month] ?? 0) + $row->realized_amount_cents;
                }
            }
        }

        $rows = [];

        for ($month = $start; $month->lte(CarbonImmutable::now($timezone)); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $rows[] = [
                'month' => $month->format('m/Y'),
                'key' => $key,
                'tenants' => (int) ($tenants[$key] ?? 0),
                'sites' => (int) ($sites[$key] ?? 0),
                'requests' => (int) ($requests[$key] ?? 0),
                'appointments' => (int) ($appointments[$key] ?? 0),
                'appointments_done' => (int) ($done[$key] ?? 0),
                'completed' => (int) ($completed[$key] ?? 0),
                'revenue_cents' => (int) ($revenue[$key] ?? 0),
                'revenue_unknown' => (int) ($unknown[$key] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  Builder<ServiceRequest>|Builder<Appointment>|Builder<Service>  $query
     * @return Collection<int|string, int>
     */
    private function countBy(Builder $query): Collection
    {
        return $query->groupBy('tenant_id')->selectRaw('tenant_id, COUNT(*) AS total')->pluck('total', 'tenant_id');
    }
}
