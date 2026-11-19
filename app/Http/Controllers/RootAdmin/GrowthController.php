<?php

namespace App\Http\Controllers\RootAdmin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantWebsite;
use App\Models\WebsiteVersion;
use App\Services\Growth\GrowthMetrics;
use App\Support\CsvExport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Crescimento e SEO: real activity and technical SEO of each company (GrowthMetrics). A month filter scopes the
 * activity; the list is paginated and every metric of the page is read with grouped queries. "Indexável" never claims
 * a page is indexed by a search engine. The same filters export to CSV.
 */
class GrowthController extends Controller
{
    public function __invoke(Request $request, GrowthMetrics $metrics): Response
    {
        $month = $this->month($request->string('mes')->value());
        $homologIds = Tenant::query()->where('is_homolog', true)->pluck('id');
        $tenants = $this->query($request)->paginate(20)->withQueryString();
        $tenants->setCollection(collect($this->rows($tenants->getCollection(), $month, $metrics)));

        return Inertia::render('rootadmin/growth/index', [
            'filters' => ['mes' => $month->format('Y-m'), 'q' => $request->string('q')->trim()->value(), 'site' => $request->string('site')->value()],
            'months' => $this->monthOptions(),
            'period' => $month->format('m/Y'),
            'overview' => $this->overview($month->startOfMonth(), $month->endOfMonth(), $homologIds),
            'evolution' => $metrics->evolution(null, $homologIds),
            'tenants' => $tenants,
            'minRequestsForConversion' => GrowthMetrics::MIN_REQUESTS_FOR_CONVERSION,
        ]);
    }

    /**
     * The same list as the screen (all pages), as CSV, in chunks.
     */
    public function export(Request $request, GrowthMetrics $metrics): StreamedResponse
    {
        $month = $this->month($request->string('mes')->value());

        return CsvExport::download('crescimento-'.$month->format('Y-m').'.csv', [
            'Empresa', 'Homologação', 'Site', 'Serviços ativos', 'Serviços no site', 'Solicitações', 'Agendamentos', 'Concluídos', 'Conversão (%)', 'Título SEO', 'Descrição SEO', 'Indexável', 'Endereço canônico',
        ], function (callable $write) use ($request, $month, $metrics): void {
            $this->query($request)->chunk(200, function (Collection $tenants) use ($write, $month, $metrics): void {
                foreach ($this->rows($tenants, $month, $metrics) as $row) {
                    $write([
                        $row['name'], $row['is_homolog'] ? 'sim' : 'não', $row['site'], $row['active_services'], $row['public_services'], $row['requests'],
                        $row['appointments'], $row['completed'], $row['conversion'] ?? 'dados insuficientes', $row['seo']['title'] ? 'sim' : 'não',
                        $row['seo']['description'] ? 'sim' : 'não', $row['seo']['indexable'] ? 'sim' : 'não', $row['seo']['canonical'] ?? '',
                    ]);
                }
            });
        });
    }

    /**
     * @return Builder<Tenant>
     */
    private function query(Request $request): Builder
    {
        $term = $request->string('q')->trim()->value();
        $site = $request->string('site')->value();
        $live = fn () => TenantWebsite::query()->where('status', 'published')->whereNotNull('published_version_id')->select('tenant_id');

        return Tenant::query()
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($site === 'publicado', fn ($query) => $query->whereIn('id', $live()))
            ->when($site === 'sem-site', fn ($query) => $query->whereNotIn('id', $live()))
            ->orderBy('name');
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $tenants, CarbonImmutable $month, GrowthMetrics $metrics): array
    {
        $ids = $tenants->pluck('id');
        $activity = $metrics->activity($ids, $month->startOfMonth(), $month->endOfMonth());
        ['websites' => $websites, 'versions' => $versions] = $metrics->sites($ids);

        return array_values($tenants->map(function (Tenant $tenant) use ($activity, $websites, $versions, $metrics): array {
            $requests = (int) ($activity['requests'][$tenant->id] ?? 0);
            $completed = (int) ($activity['completed'][$tenant->id] ?? 0);

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'is_homolog' => $tenant->isHomolog(),
                ...$metrics->site($tenant, $websites->get($tenant->id), $versions),
                'active_services' => (int) ($activity['active_services'][$tenant->id] ?? 0),
                'requests' => $requests,
                'appointments' => (int) ($activity['appointments'][$tenant->id] ?? 0),
                'completed' => $completed,
                'conversion' => $metrics->conversion($requests, $completed),
            ];
        })->all());
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
