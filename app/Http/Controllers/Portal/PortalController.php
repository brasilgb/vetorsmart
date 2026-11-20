<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalListing;
use App\Services\Location\MunicipalityDirectory;
use App\Services\Portal\PortalReferral;
use App\Services\Portal\PortalSearch;
use App\Services\Portal\PortalText;
use App\Services\Websites\SegmentTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

/**
 * Regional Portal (portal.<domain>): public directory where a visitor finds companies by service and region and goes
 * to their own site. No login, no administrative screen, no payment. It shows only published, consented information
 * of eligible companies (PortalSearch) and never a phone or e-mail. Every visitor action leads to the company's own
 * site and its request form, with the origin "Portal Regional".
 *
 * Addresses. Each listing page has one address:
 *
 * - /categorias/{categoria}, /regioes/{uf}, /regioes/{uf}/{municipio}, /regioes/{uf}/{municipio}/{categoria};
 * - the same filters sent as a query string on "/" (the search form, links of PORTAL-02) move to that address with a
 *   301, so one list never lives at two indexable addresses;
 * - free text (service, company name) and any other combination stay on "/?..." and are never indexed.
 *
 * A municipality exists only while a participating company is based there; an unknown category, state or
 * municipality answers 404. Indexable: the home and the first page of a friendly address with companies, without
 * extra filters. The sitemap lists exactly those pages.
 */
class PortalController extends Controller
{
    private const array FILTERS = ['q', 'cidade', 'uf', 'categoria', 'nome'];

    public function __construct(private PortalSearch $search) {}

    /**
     * Home, and searches by query string.
     */
    public function index(Request $request): RedirectResponse|Response
    {
        $query = $this->queryFilters($request);
        $friendly = $this->friendlyFromQuery($query);

        if ($friendly !== null) {
            $page = $request->query('pagina');

            return redirect()->away(rtrim($this->home(), '/').$friendly.(is_string($page) && ctype_digit($page) && $page !== '1' ? '?pagina='.$page : ''), 301);
        }

        return $this->render($request, $query, null);
    }

    public function category(Request $request, string $categoria): Response
    {
        if ($this->categoryName($categoria) === null) {
            return $this->notFound();
        }

        return $this->render($request, $this->queryFilters($request), ['categoria' => $categoria]);
    }

    public function region(Request $request, string $uf, ?string $municipio = null, ?string $categoria = null): RedirectResponse|Response
    {
        if ($uf !== strtolower($uf)) {
            return redirect()->away(rtrim($this->home(), '/').'/'.implode('/', array_filter(['regioes', strtolower($uf), $municipio, $categoria])).$this->queryString($request), 301);
        }

        $state = PortalText::state($uf);
        $city = $state === null || $municipio === null ? null : $this->search->municipality($state, $municipio);

        if ($state === null || ($municipio !== null && $city === null) || ($categoria !== null && $this->categoryName($categoria) === null)) {
            return $this->notFound();
        }

        return $this->render($request, $this->queryFilters($request), array_filter([
            'uf' => $state,
            'cidade' => $city,
            'categoria' => $categoria,
        ]), $municipio);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: ".$this->home()."sitemap.xml\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * The home and every friendly address that has companies right now: categories, states, municipalities where a
     * company is based and the categories of each municipality. Nothing for places without companies.
     */
    public function sitemap(): Response
    {
        $paths = [''];

        foreach ($this->search->categoriesWithProviders() as $slug) {
            $paths[] = 'categorias/'.$slug;
        }

        foreach ($this->search->places() as $place) {
            $paths[] = 'regioes/'.strtolower($place['state']);
            $paths[] = 'regioes/'.strtolower($place['state']).'/'.$place['slug'];

            foreach ($place['categories'] as $slug) {
                $paths[] = 'regioes/'.strtolower($place['state']).'/'.$place['slug'].'/'.$slug;
            }
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach (array_unique($paths) as $path) {
            $body .= '<url><loc>'.e($this->home().$path).'</loc></url>';
        }

        return response($body.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Render a list. $path holds the filters that come from the friendly address (they win over the query string);
     * the query string may add free text or other filters, which make the page a non-indexable search.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, string>|null  $path
     */
    private function render(Request $request, array $query, ?array $path, ?string $citySlug = null): Response
    {
        $validator = $this->validator($query);
        $invalid = $validator->fails();
        $filters = [];

        foreach (self::FILTERS as $field) {
            $value = $path[$field] ?? $query[$field] ?? null;
            $filters[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        $extraFilters = array_diff_key(array_filter($filters), $path ?? []);
        $searched = array_filter($filters) !== [];
        $listAll = ! $searched && $request->has('pagina');
        $page = (int) $request->query('pagina', '1');
        $seed = now()->toDateString();
        $categories = $this->categories();
        $places = $this->search->places();
        $categoryName = $filters['categoria'] === null ? null : $this->categoryName($filters['categoria']);

        $results = null;

        if (($searched || $listAll) && ! $invalid) {
            // Page links keep the address of this page (friendly or not) and its query string.
            $results = $this->search->search($filters, $page, $seed)->withQueryString();
        }

        $featured = ! $searched && ! $listAll && ! $invalid ? $this->search->search([], 1, $seed, 6) : null;
        $hasProviders = $this->search->hasProviders();
        // The municipality searched, in the registry: it travels to the company site (IBGE code only) and fills its form.
        $municipality = $filters['cidade'] === null ? null : app(MunicipalityDirectory::class)->fromText($filters['cidade'], $filters['uf']);
        // Why a search found nothing: no company in the portal at all, no company serving the municipality, or
        // companies there that do not match the other criteria.
        $emptyReason = null;

        if ($results !== null && $results->total() === 0) {
            $emptyReason = match (true) {
                ! $hasProviders => 'portal',
                $filters['cidade'] !== null && $this->search->search(['cidade' => $filters['cidade'], 'uf' => $filters['uf']], 1, $seed, 1)->total() === 0 => 'region',
                default => 'match',
            };
        }
        $friendly = $path !== null && $extraFilters === [];
        $indexable = ! $invalid && ! $listAll && (! $searched || ($friendly && $results !== null && $results->total() > 0 && $results->currentPage() === 1));
        $place = $this->place($filters, $citySlug);

        return response()->view('portal.index', [
            ...$this->baseView(),
            'filters' => $filters,
            'searchErrors' => $validator->errors(),
            'searched' => $searched || $listAll,
            'results' => $results,
            'featured' => $featured,
            'categories' => $categories,
            'categoryName' => $categoryName,
            'places' => $places,
            'regionCategories' => $path !== null ? $this->regionCategories($places, $categories, $filters, $citySlug) : [],
            'regionCities' => $path !== null && $filters['uf'] !== null && $citySlug === null ? array_values(array_filter($places, fn (array $item): bool => $item['state'] === $filters['uf'])) : [],
            'place' => $place,
            'friendlyHeading' => $path !== null ? $this->heading($categoryName, $place) : null,
            'activeFilters' => $this->activeFilters($filters, $categoryName),
            'suggestions' => $results !== null && $results->total() === 0 ? $this->suggestions($filters, $categoryName) : [],
            'emptyReason' => $emptyReason,
            'hasProviders' => $hasProviders,
            'municipality' => $municipality,
            'stateCoverage' => $emptyReason === 'region' && $filters['uf'] !== null && $this->search->search(['uf' => $filters['uf']], 1, $seed, 1)->total() > 0,
            'breadcrumbs' => $path === null ? [] : $this->breadcrumbs($categoryName, $place),
            'canonical' => $this->home().($path === null ? '' : ltrim($request->path(), '/')),
            'indexable' => $indexable,
            'title' => $this->title($filters, $categoryName, $place, $searched, $listAll),
            'description' => $this->description($categoryName, $place),
            'keepText' => $this->keepText($filters),
        ], $invalid ? 422 : 200)->withHeaders($indexable ? [] : ['X-Robots-Tag' => 'noindex, follow']);
    }

    /**
     * A category, state or municipality without a page: the portal itself answers, with its search, and status 404.
     */
    private function notFound(): Response
    {
        return response()->view('portal.index', [
            ...$this->baseView(),
            'filters' => array_fill_keys(self::FILTERS, null),
            'searchErrors' => new MessageBag,
            'searched' => true,
            'results' => null,
            'featured' => null,
            'categories' => $this->categories(),
            'categoryName' => null,
            'places' => $this->search->places(),
            'regionCategories' => [],
            'regionCities' => [],
            'place' => null,
            'friendlyHeading' => null,
            'activeFilters' => [],
            'suggestions' => [],
            'emptyReason' => null,
            'hasProviders' => $this->search->hasProviders(),
            'municipality' => null,
            'stateCoverage' => false,
            'breadcrumbs' => [],
            'canonical' => $this->home(),
            'indexable' => false,
            'title' => 'Página não encontrada · Portal Regional VetorSmart',
            'description' => 'Esta categoria ou região não tem empresas participantes no Portal Regional VetorSmart.',
            'notFound' => true,
            'keepText' => '',
        ], 404)->withHeaders(['X-Robots-Tag' => 'noindex, follow']);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseView(): array
    {
        return [
            'notFound' => false,
            'home' => $this->home(),
            'assets' => rtrim((string) config('platform.public_url'), '/'),
            'siteBase' => fn (PortalListing $listing): string => $this->siteBase($listing),
            // Signed attribution of the link (see PortalReferral): the site can trust that the visit came from here.
            'portalQuery' => fn (PortalListing $listing): string => app(PortalReferral::class)->query((int) $listing->tenant_id),
        ];
    }

    /**
     * The query string filters, with the state in upper case.
     *
     * @return array<string, mixed>
     */
    private function queryFilters(Request $request): array
    {
        $query = $request->query();

        if (is_string($query['uf'] ?? null)) {
            $query['uf'] = strtoupper(trim($query['uf']));
        }

        // The municipality field accepts "Salvador do Sul - RS" (as the suggestions and the location button fill it):
        // the state goes to its own filter, so the address and the search are the same as choosing both apart.
        if (is_string($query['cidade'] ?? null) && preg_match('/^(.+?)\s*(?:-|,|\/)\s*([A-Za-z]{2})$/u', trim($query['cidade']), $match) === 1 && PortalText::state($match[2]) !== null) {
            $state = PortalText::state($match[2]);

            if (($query['uf'] ?? '') === '' || $query['uf'] === $state) {
                $query['cidade'] = trim($match[1]);
                $query['uf'] = $state;
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function validator(array $query): ValidationValidator
    {
        return Validator::make($query, [
            'q' => ['nullable', 'string', 'max:80'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'uf' => ['nullable', 'string', Rule::in(PortalText::STATES)],
            'categoria' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'nome' => ['nullable', 'string', 'max:80'],
            'pagina' => ['nullable', 'integer', 'min:1', 'max:'.PortalSearch::MAX_PAGE],
        ], [
            'q.max' => 'Descreva o serviço com até 80 caracteres.',
            'cidade.max' => 'Informe o município com até 80 caracteres.',
            'nome.max' => 'Informe o nome com até 80 caracteres.',
            'uf.in' => 'Escolha uma UF da lista.',
            'categoria.max' => 'Categoria inválida.',
            'categoria.regex' => 'Categoria inválida.',
            'pagina.max' => 'Refine a busca para ver mais resultados.',
            'pagina.integer' => 'Página inválida.',
            'pagina.min' => 'Página inválida.',
        ]);
    }

    /**
     * Friendly address of a query string made only of category, state and municipality (and the page), or null.
     *
     * @param  array<string, mixed>  $query
     */
    private function friendlyFromQuery(array $query): ?string
    {
        foreach ($query as $key => $value) {
            if (! in_array($key, ['categoria', 'uf', 'cidade', 'pagina'], true) || ! is_string($value)) {
                return null;
            }
        }

        $category = ($query['categoria'] ?? '') !== '' ? (string) $query['categoria'] : null;
        $state = ($query['uf'] ?? '') !== '' ? PortalText::state((string) $query['uf']) : null;
        $cityText = trim((string) ($query['cidade'] ?? ''));

        if (($category !== null && $this->categoryName($category) === null) || (($query['uf'] ?? '') !== '' && $state === null)) {
            return null;
        }

        $citySlug = $cityText === '' ? null : PortalText::citySlug($cityText);

        return match (true) {
            $citySlug !== null => $state !== null && $citySlug !== '' && $this->search->municipality($state, $citySlug) !== null
                ? '/regioes/'.strtolower($state).'/'.$citySlug.($category !== null ? '/'.$category : '')
                : null,
            $state !== null => $category === null ? '/regioes/'.strtolower($state) : null,
            $category !== null => '/categorias/'.$category,
            default => null,
        };
    }

    private function categoryName(string $slug): ?string
    {
        foreach ($this->search->categories() as $category) {
            if ($category['slug'] === $slug) {
                return $category['name'];
            }
        }

        return null;
    }

    /**
     * Active segments that have companies in the portal, with the icon of their segment theme.
     *
     * @return list<array{slug: string, name: string, icon: string}>
     */
    private function categories(): array
    {
        $available = $this->search->categoriesWithProviders();
        $categories = [];

        foreach ($this->search->categories() as $category) {
            if (in_array($category['slug'], $available, true)) {
                $categories[] = [...$category, 'icon' => SegmentTheme::for($category['slug'])['icon']];
            }
        }

        return $categories;
    }

    /**
     * Categories with companies based in the current state or municipality, for the "categories in this region" list.
     *
     * @param  list<array{state: string, city: string, slug: string, categories: list<string>}>  $places
     * @param  list<array{slug: string, name: string, icon: string}>  $categories
     * @param  array<string, string|null>  $filters
     * @return list<array{slug: string, name: string, icon: string, url: string}>
     */
    private function regionCategories(array $places, array $categories, array $filters, ?string $citySlug): array
    {
        if ($filters['uf'] === null) {
            return [];
        }

        $present = [];

        foreach ($places as $place) {
            if ($place['state'] === $filters['uf'] && ($citySlug === null || $place['slug'] === $citySlug)) {
                $present = [...$present, ...$place['categories']];
            }
        }

        $region = [];

        foreach ($categories as $category) {
            if (in_array($category['slug'], $present, true)) {
                $region[] = [...$category, 'url' => $this->addressFor([...$filters, 'categoria' => $category['slug']])];
            }
        }

        return $region;
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array{state: string, state_name: string, city: string|null, slug: string|null}|null
     */
    private function place(array $filters, ?string $citySlug): ?array
    {
        if ($filters['uf'] === null) {
            return null;
        }

        return [
            'state' => $filters['uf'],
            'state_name' => PortalText::STATE_NAMES[$filters['uf']] ?? $filters['uf'],
            'city' => $citySlug !== null ? $filters['cidade'] : null,
            'slug' => $citySlug,
        ];
    }

    /**
     * Free text typed by the visitor (service, company name), kept on the links of the page.
     *
     * @param  array<string, string|null>  $filters
     */
    private function keepText(array $filters): string
    {
        $query = http_build_query(array_filter(['q' => $filters['q'], 'nome' => $filters['nome']], fn (?string $value): bool => $value !== null));

        return $query === '' ? '' : '?'.$query;
    }

    /**
     * Each active filter with the address of the same list without it. Removing the state also removes the
     * municipality, which belongs to it.
     *
     * @param  array<string, string|null>  $filters
     * @return list<array{label: string, remove: string}>
     */
    private function activeFilters(array $filters, ?string $categoryName): array
    {
        $labels = ['q' => 'Serviço: ', 'cidade' => 'Município: ', 'uf' => 'UF: ', 'categoria' => 'Categoria: ', 'nome' => 'Empresa: '];
        $active = [];

        foreach ($labels as $field => $label) {
            if ($filters[$field] === null) {
                continue;
            }

            $remaining = [...$filters, $field => null];

            if ($field === 'uf') {
                $remaining['cidade'] = null;
            }

            $active[] = ['label' => $label.($field === 'categoria' ? ($categoryName ?? $filters[$field]) : $filters[$field]), 'remove' => $this->addressFor($remaining)];
        }

        return $active;
    }

    /**
     * Address of a set of filters: the friendly one when it exists (with the free text kept as query string),
     * otherwise the query string on "/".
     *
     * @param  array<string, string|null>  $filters
     */
    private function addressFor(array $filters): string
    {
        $location = array_filter(['categoria' => $filters['categoria'], 'uf' => $filters['uf'], 'cidade' => $filters['cidade']], fn (?string $value): bool => $value !== null);
        $friendly = $location === [] ? null : $this->friendlyFromQuery($location);

        if ($friendly !== null) {
            return $friendly.$this->keepText($filters);
        }

        $query = http_build_query(array_filter($filters, fn (?string $value): bool => $value !== null));

        return $query === '' ? '/' : '/?'.$query;
    }

    /**
     * Wider searches to offer when nothing was found, each one removing a single restriction.
     *
     * @param  array<string, string|null>  $filters
     * @return list<array{label: string, url: string}>
     */
    private function suggestions(array $filters, ?string $categoryName): array
    {
        $suggestions = [];

        if ($filters['nome'] !== null) {
            $suggestions[] = ['label' => 'Buscar sem o nome da empresa', 'url' => $this->addressFor([...$filters, 'nome' => null])];
        }

        if ($filters['q'] !== null && ($categoryName !== null || $filters['cidade'] !== null)) {
            $suggestions[] = ['label' => 'Ver todos os profissionais'.($categoryName !== null ? ' de '.$categoryName : '').($filters['cidade'] !== null ? ' em '.$filters['cidade'] : ''), 'url' => $this->addressFor([...$filters, 'q' => null])];
        }

        if ($filters['cidade'] !== null) {
            $suggestions[] = ['label' => $filters['uf'] !== null ? 'Ver todo o estado ('.$filters['uf'].')' : 'Buscar em qualquer município', 'url' => $this->addressFor([...$filters, 'cidade' => null])];
        }

        if ($filters['categoria'] !== null && $filters['q'] === null) {
            $suggestions[] = ['label' => 'Ver todas as categorias'.($filters['cidade'] !== null ? ' em '.$filters['cidade'] : ''), 'url' => $this->addressFor([...$filters, 'categoria' => null])];
        }

        $suggestions[] = ['label' => 'Ver todos os profissionais do portal', 'url' => '/?pagina=1'];

        return $suggestions;
    }

    /**
     * @param  array{state: string, state_name: string, city: string|null, slug: string|null}|null  $place
     * @return list<array{name: string, url: string|null}>
     */
    private function breadcrumbs(?string $categoryName, ?array $place): array
    {
        $items = [['name' => 'Início', 'url' => '/']];

        if ($place !== null) {
            $items[] = ['name' => $place['state_name'], 'url' => '/regioes/'.strtolower($place['state'])];

            if ($place['city'] !== null) {
                $items[] = ['name' => $place['city'], 'url' => '/regioes/'.strtolower($place['state']).'/'.$place['slug']];
            }
        }

        if ($categoryName !== null) {
            $items[] = ['name' => $categoryName, 'url' => null];
        }

        // The last item is the current page: it has no link.
        $last = count($items) - 1;

        return array_map(fn (array $item, int $index): array => ['name' => $item['name'], 'url' => $index === $last ? null : $item['url']], $items, array_keys($items));
    }

    /**
     * @param  array{state: string, state_name: string, city: string|null, slug: string|null}|null  $place
     */
    private function heading(?string $categoryName, ?array $place): string
    {
        $where = $place === null ? null : ($place['city'] !== null ? $place['city'].' - '.$place['state'] : $place['state_name']);

        return match (true) {
            $categoryName !== null && $where !== null => $categoryName.' em '.$where,
            $categoryName !== null => $categoryName,
            $where !== null => 'Profissionais em '.$where,
            default => 'Resultados da busca',
        };
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  array{state: string, state_name: string, city: string|null, slug: string|null}|null  $place
     */
    private function title(array $filters, ?string $categoryName, ?array $place, bool $searched, bool $listAll): string
    {
        $suffix = ' · Portal Regional VetorSmart';

        return match (true) {
            $listAll => 'Todos os profissionais'.$suffix,
            ! $searched => 'Encontre profissionais na sua região'.$suffix,
            $filters['q'] !== null => Str::ucfirst($filters['q']).(($where = trim(implode(' - ', array_filter([$filters['cidade'], $filters['uf']])))) !== '' ? ' em '.$where : '').$suffix,
            default => $this->heading($categoryName, $place).$suffix,
        };
    }

    /**
     * @param  array{state: string, state_name: string, city: string|null, slug: string|null}|null  $place
     */
    private function description(?string $categoryName, ?array $place): string
    {
        $where = $place === null ? null : ($place['city'] !== null ? $place['city'].' - '.$place['state'] : $place['state_name']);
        $end = ' que participam do Portal Regional VetorSmart. Veja serviços e região atendida e solicite atendimento diretamente à empresa.';

        return match (true) {
            $categoryName !== null && $where !== null => 'Empresas de '.$categoryName.' em '.$where.$end,
            $categoryName !== null => 'Empresas de '.$categoryName.$end,
            $where !== null => 'Prestadores de serviço em '.$where.$end,
            default => 'Encontre prestadores de serviço por tipo de serviço e município, conheça o site de cada empresa e solicite atendimento diretamente a ela, sem custo.',
        };
    }

    private function queryString(Request $request): string
    {
        $query = $request->getQueryString();

        return $query === null || $query === '' ? '' : '?'.$query;
    }

    /**
     * Standard address of a company's own site (always available, whatever its other addresses). Built from the slugs
     * the search already read, so no extra query per company.
     */
    private function siteBase(PortalListing $listing): string
    {
        return config('platform.public_url').'/'.$listing->getAttribute('segment_slug').'/'.$listing->getAttribute('tenant_slug');
    }

    private function home(): string
    {
        return config('platform.public_scheme', 'https').'://'.config('platform.portal_host').'/';
    }
}
