<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalListing;
use App\Services\Portal\PortalSearch;
use App\Services\Portal\PortalText;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Regional Portal (portal.<domain>): public directory where a visitor finds companies by service and region and goes
 * to their own site. No login, no administrative screen, no payment. It shows only published, consented information
 * of eligible companies (PortalSearch) and never a phone or e-mail. Every visitor action leads to the company's own
 * site and its request form, with the origin "Portal Regional".
 */
class PortalController extends Controller
{
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
            'uf.in' => 'Escolha uma UF válida.',
            'max' => 'Use no máximo :max caracteres.',
            'pagina.max' => 'Refine a busca para ver mais resultados.',
            'categoria.regex' => 'Categoria inválida.',
            'pagina.integer' => 'Página inválida.',
            'pagina.min' => 'Página inválida.',
        ]);

        $filters = [];

        foreach (['q', 'cidade', 'uf', 'categoria', 'nome'] as $field) {
            $value = $query[$field] ?? null;
            $filters[$field] = is_string($value) ? trim($value) : null;
        }

        $searched = collect($filters)->filter(fn (?string $value): bool => $value !== null && $value !== '')->isNotEmpty();
        $results = null;

        if ($searched && ! $validator->fails()) {
            $results = $this->search->search($filters, (int) $request->query('pagina', '1'), now()->toDateString());
            $results->withQueryString();
        }

        // Only the home without filters is indexed; result pages are not, so filter combinations never become
        // thousands of thin indexable pages.
        return response()->view('portal.index', [
            'filters' => $filters,
            'searchErrors' => $validator->errors(),
            'searched' => $searched,
            'results' => $results,
            'categories' => $this->search->categories(),
            'cities' => $this->search->cities(),
            'canonical' => $this->home(),
            'siteBase' => fn (PortalListing $listing): string => $this->siteBase($listing),
        ], $validator->fails() && $searched ? 422 : 200)->withHeaders($searched ? ['X-Robots-Tag' => 'noindex, follow'] : []);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: ".$this->home()."sitemap.xml\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>'.e($this->home()).'</loc></url></urlset>';

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
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
