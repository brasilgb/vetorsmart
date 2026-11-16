<?php

namespace App\Http\Controllers\Site;

use App\Enums\DomainKind;
use App\Http\Controllers\Controller;
use App\Models\AssistantSetting;
use App\Models\Tenant;
use App\Services\Websites\PublicSiteAddress;
use App\Services\Websites\WebsiteResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public company sites. Two ways in, one canonical address:
 *
 * - the standard address /{segment}/{company} on the platform's main domain, always available;
 * - the company's own verified domain (https://www.empresa.com.br), when it has one, which serves the same site.
 *
 * The canonical address is the custom domain when there is one, otherwise the standard address, so search engines
 * index a single copy. Earlier platform subdomains only redirect to the standard address. A missing, unpublished or
 * suspended site always answers with a plain 404 that reveals nothing about the company.
 */
class SiteController extends Controller
{
    public function __construct(private WebsiteResolver $resolver, private PublicSiteAddress $address) {}

    public function page(string $segment, string $company): View
    {
        $site = $this->resolver->resolvePath($segment, $company) ?? abort(404);

        return $this->render($site['tenant'], $site['content'], 'home', (string) $this->address->home($site['tenant']), false);
    }

    /**
     * The services the company chose to publish. Without any, the page does not exist, so no empty page gets indexed.
     */
    public function services(string $segment, string $company): View
    {
        $site = $this->resolver->resolvePath($segment, $company) ?? abort(404);

        if (empty($site['content']['services'] ?? [])) {
            abort(404);
        }

        return $this->render($site['tenant'], $site['content'], 'services', (string) $this->address->home($site['tenant']), false);
    }

    /**
     * Home of a company on its own domain. An earlier platform subdomain redirects to the standard address.
     */
    public function hostPage(Request $request): RedirectResponse|View
    {
        return $this->onHost($request, '', 'home');
    }

    public function hostServices(Request $request): RedirectResponse|View
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
                if ($this->address->customHost($site['tenant']) === null) {
                    array_push($locations, ...$this->indexableLocations($site['tenant'], $site['content']));
                }
            }
        } else {
            $site = $this->resolver->resolve($request->getHost());

            if ($site === null || ! $this->servesOnThisHost($site['tenant'], $request->getHost(), $site['domain']->kind)) {
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
     * robots.txt. The central host and a company domain that serves its site allow crawling and point to their own
     * sitemap; an earlier platform subdomain only lets crawlers follow its redirect; any other host is closed.
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

        if ($site['domain']->kind === DomainKind::Custom && $this->servesOnThisHost($site['tenant'], $request->getHost(), DomainKind::Custom)) {
            $base = $this->address->hostBase($request->getHost());

            return response("User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n", 200, $headers);
        }

        return response("User-agent: *\nDisallow:\n", 200, $headers);
    }

    /**
     * A request on a company hostname. The company's own domain serves the site; another verified domain of the same
     * company moves to that one; an earlier platform subdomain moves to the standard address.
     */
    private function onHost(Request $request, string $suffix, string $page): RedirectResponse|View
    {
        $site = $this->resolver->resolve($request->getHost()) ?? abort(404);
        $tenant = $site['tenant'];

        if ($site['domain']->kind === DomainKind::Subdomain) {
            $target = $this->address->home($tenant) ?? abort(404);

            return redirect()->away($target.$suffix, 301);
        }

        $customHost = $this->address->customHost($tenant);

        if ($customHost !== null && $customHost !== $request->getHost()) {
            return redirect()->away($this->address->hostBase($customHost).($suffix === '' ? '/' : $suffix), 301);
        }

        if ($page === 'services' && empty($site['content']['services'] ?? [])) {
            abort(404);
        }

        return $this->render($tenant, $site['content'], $page, $this->address->hostBase($request->getHost()), true);
    }

    /**
     * Whether this hostname is where the company's site is served (its main verified custom domain).
     */
    private function servesOnThisHost(Tenant $tenant, string $host, DomainKind $kind): bool
    {
        return $kind === DomainKind::Custom && $this->address->customHost($tenant) === $host;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    private function indexableLocations(Tenant $tenant, array $content): array
    {
        if (! ($content['seo']['indexable'] ?? true)) {
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
     * @param  array<string, mixed>  $content
     */
    private function render(Tenant $tenant, array $content, string $page, string $base, bool $onCustomDomain): View
    {
        $assistantEnabled = (bool) AssistantSetting::query()->forTenant($tenant)->value('enabled');
        $home = $onCustomDomain ? $base.'/' : $base;

        return view('website.site', [
            'content' => $content,
            'canonical' => $this->address->canonical($tenant, $page === 'services' ? '/servicos' : ''),
            'noindex' => ! ($content['seo']['indexable'] ?? true),
            'preview' => false,
            'page' => $page,
            'links' => [
                'home' => $home,
                'services' => empty($content['services'] ?? []) ? null : $base.'/servicos',
                'assistant' => $assistantEnabled ? $base.'/atendimento' : null,
            ],
        ]);
    }
}
