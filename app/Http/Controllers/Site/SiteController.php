<?php

namespace App\Http\Controllers\Site;

use App\Enums\DomainKind;
use App\Http\Controllers\Controller;
use App\Models\AssistantSetting;
use App\Models\Tenant;
use App\Services\Websites\PublicSiteAddress;
use App\Services\Websites\SegmentTheme;
use App\Services\Websites\WebsiteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public company sites. Up to three ways in, one canonical address:
 *
 * - the standard address /{segment}/{company} on the platform's main domain, always available;
 * - the company's platform subdomain ({name}.<tenant_base_domain>), which serves the site when subdomain sites are
 *   enabled and otherwise redirects to the standard address; an earlier subdomain redirects to the current one;
 * - the company's own verified domain (https://www.empresa.com.br), when it has one, which serves the same site.
 *
 * The canonical address is the custom domain, else the subdomain (when enabled), else the standard address, so search
 * engines index a single copy. A missing, unpublished or suspended site always answers with a plain 404 that reveals
 * nothing about the company.
 */
class SiteController extends Controller
{
    public function __construct(private WebsiteResolver $resolver, private PublicSiteAddress $address) {}

    public function page(string $segment, string $company): Response
    {
        $site = $this->resolver->resolvePath($segment, $company) ?? abort(404);

        return $this->render($site['tenant'], $site['content'], 'home', (string) $this->address->home($site['tenant']), false);
    }

    /**
     * The services the company chose to publish. Without any, the page does not exist, so no empty page gets indexed.
     */
    public function services(string $segment, string $company): Response
    {
        $site = $this->resolver->resolvePath($segment, $company) ?? abort(404);

        if (empty($site['content']['services'] ?? [])) {
            abort(404);
        }

        return $this->render($site['tenant'], $site['content'], 'services', (string) $this->address->home($site['tenant']), false);
    }

    /**
     * Home of a company on one of its hostnames: its own domain or its current subdomain serve the site, anything else
     * of the company redirects.
     */
    public function hostPage(Request $request): RedirectResponse|Response
    {
        return $this->onHost($request, '', 'home');
    }

    public function hostServices(Request $request): RedirectResponse|Response
    {
        return $this->onHost($request, '/servicos', 'services');
    }

    /**
     * Sitemap. The central host lists every live, indexable site whose canonical address is a standard one; a company
     * domain lists its own pages. A sitemap never lists addresses of another host.
     */
    public function sitemap(Request $request): Response
    {
        $locations = [];

        if ($this->isCentral($request)) {
            $locations[] = config('platform.public_url').'/';

            foreach ($this->resolver->liveSites() as $site) {
                if ($this->address->canonicalHost($site['tenant']) === null) {
                    array_push($locations, ...$this->indexableLocations($site['tenant'], $site['content']));
                }
            }
        } else {
            $site = $this->resolver->resolve($request->getHost());

            if ($site === null || ! $this->isCanonicalHost($site['tenant'], $request->getHost())) {
                abort(404);
            }

            $locations = $this->indexableLocations($site['tenant'], $site['content']);
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($locations as $location) {
            $body .= '<url><loc>'.e($location).'</loc></url>';
        }

        $body .= '</urlset>';

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * robots.txt. The central host and the canonical host of a company allow crawling and point to their own sitemap;
     * another hostname of a company (which redirects, or serves the site with its canonical elsewhere) lets crawlers
     * follow it; any other host is closed.
     */
    public function robots(Request $request): Response
    {
        $headers = ['Content-Type' => 'text/plain; charset=UTF-8'];

        if ($this->isCentral($request)) {
            return response("User-agent: *\nDisallow: /meu-site/\n\nSitemap: ".config('platform.public_url')."/sitemap.xml\n", 200, $headers);
        }

        $site = $this->resolver->resolve($request->getHost());

        if ($site === null) {
            return response("User-agent: *\nDisallow: /\n", 200, $headers);
        }

        if ($this->isCanonicalHost($site['tenant'], $request->getHost())) {
            $base = $this->address->hostBase($request->getHost());

            return response("User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n", 200, $headers);
        }

        return response("User-agent: *\nDisallow:\n", 200, $headers);
    }

    /**
     * A request on a company hostname.
     *
     * - Platform subdomain, subdomain sites disabled: moves to the standard address.
     * - Platform subdomain, enabled: the current one serves the site; an earlier one moves to the current one.
     * - Custom domain: the main one serves the site; another verified domain of the same company moves to the main one.
     *
     * Redirects keep the path and the query string. Their target is always built from the company's own records, never
     * from the request, so they cannot be turned into an open redirect.
     */
    private function onHost(Request $request, string $suffix, string $page): RedirectResponse|Response
    {
        $site = $this->resolver->resolve($request->getHost()) ?? abort(404);
        $tenant = $site['tenant'];
        $host = $request->getHost();

        if ($site['domain']->kind === DomainKind::Subdomain) {
            if (! $this->address->subdomainSitesEnabled()) {
                $target = $this->address->home($tenant) ?? abort(404);

                return redirect()->away($target.$suffix.$this->queryString($request), 301);
            }

            $current = $this->address->subdomainHost($tenant) ?? abort(404);

            if ($current !== $host) {
                return $this->redirectToHost($request, $current, $suffix);
            }
        } else {
            $customHost = $this->address->customHost($tenant);

            if ($customHost !== null && $customHost !== $host) {
                return $this->redirectToHost($request, $customHost, $suffix);
            }
        }

        if ($page === 'services' && empty($site['content']['services'] ?? [])) {
            abort(404);
        }

        return $this->render($tenant, $site['content'], $page, $this->address->hostBase($host), true);
    }

    private function redirectToHost(Request $request, string $host, string $suffix): RedirectResponse
    {
        return redirect()->away($this->address->hostBase($host).($suffix === '' ? '/' : $suffix).$this->queryString($request), 301);
    }

    private function queryString(Request $request): string
    {
        $query = $request->getQueryString();

        return $query === null || $query === '' ? '' : '?'.$query;
    }

    /**
     * Whether this hostname is the company's canonical host (its main custom domain, or its current subdomain when
     * subdomain sites are enabled and it has no custom domain).
     */
    private function isCanonicalHost(Tenant $tenant, string $host): bool
    {
        return $this->address->canonicalHost($tenant) === $host;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    private function indexableLocations(Tenant $tenant, array $content): array
    {
        if ($tenant->isHomolog() || ! ($content['seo']['indexable'] ?? true)) {
            return [];
        }

        $locations = [(string) $this->address->canonical($tenant)];

        if (! empty($content['services'] ?? [])) {
            $locations[] = (string) $this->address->canonical($tenant, '/servicos');
        }

        return $locations;
    }

    private function isCentral(Request $request): bool
    {
        return $request->getHost() === (string) config('platform.central_host');
    }

    /**
     * Render a site page. Navigation stays on the address the visitor is using ($base, a custom domain or the standard
     * address); the canonical address is the same for every way in.
     *
     * A homologation company is never indexable, whatever its SEO setting: the page carries noindex in the markup and
     * in the X-Robots-Tag header, and a banner tells the visitor it is a test environment.
     *
     * @param  array<string, mixed>  $content
     */
    private function render(Tenant $tenant, array $content, string $page, string $base, bool $onCustomDomain): Response
    {
        $assistantEnabled = (bool) AssistantSetting::query()->forTenant($tenant)->value('enabled');
        $home = $onCustomDomain ? $base.'/' : $base;
        $noindex = $tenant->isHomolog() || ! ($content['seo']['indexable'] ?? true);

        $shareImage = $content['hero']['file'] ?? $content['logo']['file'] ?? null;

        $response = response()->view('website.site', [
            'content' => $content,
            'theme' => SegmentTheme::for($content['segment_slug'] ?? null),
            'mediaBase' => $base.'/midia/',
            'ogImage' => is_string($shareImage) ? $this->address->canonical($tenant, '/midia/'.$shareImage) : null,
            'canonical' => $this->address->canonical($tenant, $page === 'services' ? '/servicos' : ''),
            'noindex' => $noindex,
            'homolog' => $tenant->isHomolog(),
            'preview' => false,
            'page' => $page,
            'links' => [
                'home' => $home,
                'services' => empty($content['services'] ?? []) ? null : $base.'/servicos',
                'assistant' => $assistantEnabled ? $base.'/atendimento' : null,
                'request' => $base.'/solicitar',
            ],
        ]);

        if ($noindex) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
