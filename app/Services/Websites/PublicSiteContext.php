<?php

namespace App\Services\Websites;

use App\Enums\DomainKind;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * The live company site behind a public request, for the pages added next to the home: the standard address
 * /{segment}/{company} (from the two URL slugs) or the company hostname that serves the site (its main custom domain,
 * or its current platform subdomain when subdomain sites are enabled). The company never comes from the session or the
 * payload. Anything else is null, which callers answer with a plain 404.
 */
class PublicSiteContext
{
    public function __construct(private WebsiteResolver $resolver, private PublicSiteAddress $address) {}

    /**
     * @return array{tenant: Tenant, content: array<string, mixed>, base: string, on_host: bool}|null
     */
    public function forRequest(Request $request): ?array
    {
        $segment = $request->route('segment');
        $company = $request->route('company');

        if (is_string($segment) && is_string($company)) {
            $site = $this->resolver->resolvePath($segment, $company);

            return $site === null ? null : [
                'tenant' => $site['tenant'],
                'content' => $site['content'],
                'base' => (string) $this->address->home($site['tenant']),
                'on_host' => false,
            ];
        }

        $site = $this->resolver->resolve($request->getHost());

        if ($site === null) {
            return null;
        }

        $tenant = $site['tenant'];
        $host = $request->getHost();
        $serves = match ($site['domain']->kind) {
            DomainKind::Custom => $this->address->customHost($tenant) === $host,
            DomainKind::Subdomain => $this->address->subdomainSitesEnabled() && $this->address->subdomainHost($tenant) === $host,
        };

        return $serves ? ['tenant' => $tenant, 'content' => $site['content'], 'base' => $this->address->hostBase($host), 'on_host' => true] : null;
    }
}
