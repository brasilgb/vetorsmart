<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\RequestStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantWebsite;
use App\Models\WebsiteVersion;
use App\Services\Websites\PublicSiteAddress;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Crescimento e SEO: real activity and technical SEO of each company, from data already recorded (sites and their
 * published snapshots, services, requests and appointments). A month filter scopes the activity; the list is paginated
 * and every metric of the page is read with grouped queries. "Indexável" only means the local configuration allows
 * indexing (live site, SEO switch on, not homologation, company active): it never claims a page is indexed by a search
 * engine. Conversion is shown only with enough requests to mean something.
 */
class GrowthController extends Controller
{
    /** Minimum requests in the period to show a conversion rate. */
    private const int MIN_REQUESTS_FOR_CONVERSION = 5;

    public function __invoke(Request $request, PublicSiteAddress $address): Response
    {
        $month = $this->month($request->string('mes')->value());
        [$from, $to] = [$month->startOfMonth(), $month->endOfMonth()];
        $term = $request->string('q')->trim()->value();
        $site = $request->string('site')->value();
        $homologIds = Tenant::query()->where('is_homolog', true)->pluck('id');

        $tenants = Tenant::query()
            ->with('segment:id,slug,name')
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($site === 'publicado', fn ($query) => $query->whereIn('id', TenantWebsite::query()->where('status', 'published')->whereNotNull('published_version_id')->select('tenant_id')))
            ->when($site === 'sem-site', fn ($query) => $query->whereNotIn('id', TenantWebsite::query()->where('status', 'published')->whereNotNull('published_version_id')->select('tenant_id')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $ids = $tenants->getCollection()->pluck('id');
        $websites = TenantWebsite::query()->whereIn('tenant_id', $ids)->get()->keyBy('tenant_id');
        $versions = WebsiteVersion::query()->whereIn('id', $websites->pluck('published_version_id')->filter())->get(['id', 'content'])->keyBy('id');
        $activeServices = Service::query()->whereIn('tenant_id', $ids)->where('active', true)->groupBy('tenant_id')->selectRaw('tenant_id, COUNT(*) AS total')->pluck('total', 'tenant_id');
        $received = $this->countBy(ServiceRequest::query()->whereIn('tenant_id', $ids)->whereBetween('created_at', [$from, $to]));
        $completed = $this->countBy(ServiceRequest::query()->whereIn('tenant_id', $ids)->where('status', RequestStatus::Completed->value)->whereBetween('closed_at', [$from, $to]));
        $appointments = $this->countBy(Appointment::query()->whereIn('tenant_id', $ids)->whereBetween('created_at', [$from, $to]));

        return Inertia::render('rootadmin/growth/index', [
            'filters' => ['mes' => $month->format('Y-m'), 'q' => $term, 'site' => $site],
            'months' => $this->monthOptions(),
            'period' => $month->format('m/Y'),
            'overview' => $this->overview($from, $to, $homologIds),
            'evolution' => $this->evolution($homologIds),
            'tenants' => $tenants->through(function (Tenant $tenant) use ($websites, $versions, $activeServices, $received, $completed, $appointments, $address): array {
                $website = $websites->get($tenant->id);
                $live = $website?->isLive() === true;
                $content = $live ? ($versions->get($website->published_version_id)->content ?? []) : ($website->draft_content ?? []);
                $seo = (array) ($content['seo'] ?? []);
                $indexable = $live && ! $tenant->isHomolog() && $tenant->status === TenantStatus::Active && (bool) ($seo['indexable'] ?? true);
                $requests = (int) ($received[$tenant->id] ?? 0);
                $done = (int) ($completed[$tenant->id] ?? 0);

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'is_homolog' => $tenant->isHomolog(),
                    'site' => $website === null ? 'none' : ($live ? 'published' : $website->status->value),
                    'active_services' => (int) ($activeServices[$tenant->id] ?? 0),
                    'public_services' => $live ? count((array) ($content['services'] ?? [])) : 0,
                    'requests' => $requests,
                    'appointments' => (int) ($appointments[$tenant->id] ?? 0),
                    'completed' => $done,
                    'conversion' => $requests >= self::MIN_REQUESTS_FOR_CONVERSION ? (int) round($done * 100 / $requests) : null,
                    'seo' => [
                        'title' => filled($seo['title'] ?? null),
                        'description' => filled($seo['description'] ?? null),
                        'canonical' => $live ? $address->canonical($tenant) : null,
                        'indexable' => $indexable,
                        'in_sitemap' => $indexable,
                    ],
                ];
            }),
            'minRequestsForConversion' => self::MIN_REQUESTS_FOR_CONVERSION,
        ]);
    }

    /**
     * @param  Collection<int, int>  $homologIds
     * @return array<string, int>
     */
    private function overview(CarbonImmutable $from, CarbonImmutable $to, Collection $homologIds): array
    {
        $live = TenantWebsite::query()->where('status', 'published')->whereNotNull('published_version_id')->whereNotIn('tenant_id', $homologIds);
        $publishedTenants = (clone $live)->count();
        $publicServices = WebsiteVersion::query()->whereIn('id', (clone $live)->select('published_version_id'))->get(['content'])
            ->sum(fn (WebsiteVersion $version): int => count((array) ($version->content['services'] ?? [])));
        $realTenants = Tenant::query()->whereNotIn('id', $homologIds)->count();

        $active = ServiceRequest::query()->whereBetween('created_at', [$from, $to])->whereNotIn('tenant_id', $homologIds)->distinct()->pluck('tenant_id')
            ->merge(Appointment::query()->whereBetween('created_at', [$from, $to])->whereNotIn('tenant_id', $homologIds)->distinct()->pluck('tenant_id'))
            ->unique()->count();

        return [
            'with_site' => $publishedTenants,
            'without_site' => max(0, $realTenants - $publishedTenants),
            'public_services' => $publicServices,
            'active_tenants' => $active,
            'tenants' => $realTenants,
        ];
    }

    /**
     * Real monthly figures of the last six months, homologation excluded.
     *
     * @param  Collection<int, int>  $homologIds
     * @return list<array{month: string, tenants: int, sites: int, requests: int, appointments: int, completed: int}>
     */
    private function evolution(Collection $homologIds): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(5);
        $monthly = fn ($query, string $column): Collection => $query->where($column, '>=', $start)->whereNotIn('tenant_id', $homologIds)->get([$column])
            ->countBy(fn ($row): string => CarbonImmutable::parse($row->{$column})->format('Y-m'));

        $tenants = Tenant::query()->where('created_at', '>=', $start)->where('is_homolog', false)->get(['created_at'])
            ->countBy(fn (Tenant $tenant): string => $tenant->created_at?->format('Y-m') ?? '');
        $sites = $monthly(TenantWebsite::query()->whereNotNull('published_at'), 'published_at');
        $requests = $monthly(ServiceRequest::query(), 'created_at');
        $appointments = $monthly(Appointment::query(), 'created_at');
        $completed = $monthly(ServiceRequest::query()->where('status', RequestStatus::Completed->value)->whereNotNull('closed_at'), 'closed_at');

        $rows = [];

        for ($month = $start; $month->lte(CarbonImmutable::now()); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $rows[] = [
                'month' => $month->format('m/Y'),
                'tenants' => (int) ($tenants[$key] ?? 0),
                'sites' => (int) ($sites[$key] ?? 0),
                'requests' => (int) ($requests[$key] ?? 0),
                'appointments' => (int) ($appointments[$key] ?? 0),
                'completed' => (int) ($completed[$key] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  Builder<ServiceRequest>|Builder<Appointment>  $query
     * @return Collection<int|string, int>
     */
    private function countBy(Builder $query): Collection
    {
        return $query->groupBy('tenant_id')->selectRaw('tenant_id, COUNT(*) AS total')->pluck('total', 'tenant_id');
    }

    private function month(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $value);

            if ($month !== null && $month->lte(CarbonImmutable::now())) {
                return $month;
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function monthOptions(): array
    {
        $options = [];

        for ($index = 0; $index < 12; $index++) {
            $month = CarbonImmutable::now()->startOfMonth()->subMonths($index);
            $options[] = ['value' => $month->format('Y-m'), 'label' => $month->format('m/Y')];
        }

        return $options;
    }
}
