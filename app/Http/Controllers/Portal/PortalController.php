<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalListing;
use App\Services\Portal\PortalSearch;
use App\Services\Portal\PortalText;
use App\Services\Websites\SegmentTheme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Regional Portal (portal.<domain>): public directory where a visitor finds companies by service and region and goes
 * to their own site. No login, no administrative screen, no payment. It shows only published, consented information
 * of eligible companies (PortalSearch) and never a phone or e-mail. Every visitor action leads to the company's own
 * site and its request form, with the origin "Portal Regional".
 *
 * Indexing: the home and the page of a single category (only ?categoria=, first page, with companies) are indexable
 * and listed in the sitemap; every other combination of filters is noindex, so filters never become thousands of thin
 * pages.
 */
class PortalController extends Controller
{
    private const array FILTERS = ['q', 'cidade', 'uf', 'categoria', 'nome'];

    public function __construct(private PortalSearch $search) {}

    public function index(Request $request): Response
    {
        // A state typed in lower case in a shared link is still the same state.
        $query = $request->query();

        if (is_string($query['uf'] ?? null)) {
            $query['uf'] = strtoupper(trim($query['uf']));
        }

        $validator = Validator::make($query, [
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

        $filters = [];

        foreach (self::FILTERS as $field) {
            $value = $query[$field] ?? null;
            $filters[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        $searched = array_filter($filters) !== [];
        // /?pagina=N without filters lists every company of the portal, in the same neutral order.
        $listAll = ! $searched && $request->has('pagina');
        $invalid = $validator->fails();
        $page = (int) $request->query('pagina', '1');
        $seed = now()->toDateString();
        $categories = $this->categories();
        $categoryName = collect($categories)->firstWhere('slug', $filters['categoria'])['name'] ?? null;

        $results = ($searched || $listAll) && ! $invalid ? $this->search->search($filters, $page, $seed)->withQueryString() : null;
        $featured = ! $searched && ! $listAll && ! $invalid ? $this->search->search([], 1, $seed, 6) : null;

        $categoryPage = $results !== null && $categoryName !== null && array_keys(array_filter($filters)) === ['categoria'];
        $indexable = ! $invalid && ! $listAll && (! $searched || ($categoryPage && $results->total() > 0 && $results->currentPage() === 1));
        $canonical = $categoryPage ? $this->home().'?categoria='.$filters['categoria'] : $this->home();

        return response()->view('portal.index', [
            'filters' => $filters,
            'searchErrors' => $validator->errors(),
            'searched' => $searched || $listAll,
            'results' => $results,
            'featured' => $featured,
            'categories' => $categories,
            'categoryName' => $categoryName,
            'cities' => $this->search->cities(),
            'activeFilters' => $this->activeFilters($filters, $categoryName),
            'suggestions' => $results !== null && $results->total() === 0 ? $this->suggestions($filters, $categoryName) : [],
            'canonical' => $canonical,
            'indexable' => $indexable,
            'title' => $listAll ? 'Todos os profissionais · Portal Regional VetorSmart' : $this->title($filters, $categoryName, $searched),
            'home' => $this->home(),
            'assets' => rtrim((string) config('platform.public_url'), '/'),
            'siteBase' => fn (PortalListing $listing): string => $this->siteBase($listing),
        ], $invalid ? 422 : 200)->withHeaders($indexable ? [] : ['X-Robots-Tag' => 'noindex, follow']);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: ".$this->home()."sitemap.xml\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * The home and the category pages that have companies right now: a short, stable list.
     */
    public function sitemap(): Response
    {
        $locations = [$this->home()];

        foreach ($this->search->categoriesWithProviders() as $slug) {
            $locations[] = $this->home().'?categoria='.$slug;
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($locations as $location) {
            $body .= '<url><loc>'.e($location).'</loc></url>';
        }

        return response($body.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
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
     * Each active filter with the address of the same search without it.
     *
     * @param  array<string, string|null>  $filters
     * @return list<array{label: string, remove: string}>
     */
    private function activeFilters(array $filters, ?string $categoryName): array
    {
        $labels = [
            'q' => fn (string $value): string => 'Serviço: '.$value,
            'cidade' => fn (string $value): string => 'Município: '.$value,
            'uf' => fn (string $value): string => 'UF: '.$value,
            'categoria' => fn (string $value): string => 'Categoria: '.($categoryName ?? $value),
            'nome' => fn (string $value): string => 'Empresa: '.$value,
        ];
        $active = [];

        foreach ($labels as $field => $label) {
            if ($filters[$field] !== null) {
                $active[] = ['label' => $label($filters[$field]), 'remove' => $this->url([...$filters, $field => null])];
            }
        }

        return $active;
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
            $suggestions[] = ['label' => 'Buscar sem o nome da empresa', 'url' => $this->url([...$filters, 'nome' => null])];
        }

        if ($filters['cidade'] !== null) {
            $suggestions[] = ['label' => $filters['uf'] !== null ? 'Ver todo o estado ('.$filters['uf'].')' : 'Buscar em qualquer município', 'url' => $this->url([...$filters, 'cidade' => null])];
        }

        if ($filters['q'] !== null && $filters['categoria'] !== null) {
            $suggestions[] = ['label' => 'Ver todos os profissionais de '.($categoryName ?? 'esta categoria'), 'url' => $this->url([...$filters, 'q' => null])];
        } elseif ($filters['categoria'] !== null) {
            $suggestions[] = ['label' => 'Buscar em todas as categorias', 'url' => $this->url([...$filters, 'categoria' => null])];
        }

        $suggestions[] = ['label' => 'Ver todos os profissionais do portal', 'url' => '/?pagina=1'];

        return $suggestions;
    }

    /**
     * @param  array<string, string|null>  $filters
     */
    private function title(array $filters, ?string $categoryName, bool $searched): string
    {
        if (! $searched) {
            return 'Encontre profissionais na sua região · Portal Regional VetorSmart';
        }

        $what = $filters['q'] ?? $categoryName ?? 'Profissionais';
        $where = trim(implode(' - ', array_filter([$filters['cidade'], $filters['uf']])));

        return Str::ucfirst($what).($where !== '' ? ' em '.$where : '').' · Portal Regional VetorSmart';
    }

    /**
     * Relative address of the portal with these filters (empty ones are left out).
     *
     * @param  array<string, string|null>  $filters
     */
    private function url(array $filters): string
    {
        $query = http_build_query(array_filter($filters, fn (?string $value): bool => $value !== null && $value !== ''));

        return $query === '' ? '/' : '/?'.$query;
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
