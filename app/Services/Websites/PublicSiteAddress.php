<?php

namespace App\Services\Websites;

use App\Enums\DomainKind;
use App\Enums\DomainStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;

/**
 * Public addresses of a company site.
 *
 * The standard address is {public_url}/{segment}/{company} on the platform's main domain and needs no DNS. A company
 * also has a platform subdomain ({name}.<tenant_base_domain>), which serves the site once subdomain sites are enabled,
 * and may serve it on its own verified domain (https://www.empresa.com.br). Every way in answers, but there is a single
 * canonical address per company so search engines never see two indexable copies, in this order of preference: the
 * custom domain, the subdomain (when enabled), the standard address.
 */
class PublicSiteAddress
{
    public function __construct(private TenantSubdomainService $subdomains) {}

    /**
     * Path of the standard address, such as /refrigeracao/clima-total, or null without a segment.
     */
    public function path(Tenant $tenant): ?string
    {
        $tenant->loadMissing('segment');
        $segment = $tenant->segment;

        if ($segment === null || $segment->slug === '' || $tenant->slug === '') {
            return null;
        }

        return '/'.$segment->slug.'/'.$tenant->slug;
    }

    /**
     * Standard address of the site home, on the platform's main domain.
     */
    public function home(Tenant $tenant): ?string
    {
        return $this->standard($tenant, '');
    }

    public function services(Tenant $tenant): ?string
    {
        return $this->standard($tenant, '/servicos');
    }

    public function assistant(Tenant $tenant): ?string
    {
        return $this->standard($tenant, '/atendimento');
    }

    /**
     * Whether platform subdomains serve the sites (the wildcard DNS record and certificate are live).
     */
    public function subdomainSitesEnabled(): bool
    {
        return (bool) config('platform.subdomain_sites');
    }

    /**
     * The company's own verified domain that serves its site: the one marked as primary, else the oldest verified.
     */
    public function customHost(Tenant $tenant): ?string
    {
        $hostname = TenantDomain::query()
            ->forTenant($tenant)
            ->where('kind', DomainKind::Custom)
            ->where('status', DomainStatus::Verified)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->value('hostname');

        return $hostname === null ? null : (string) $hostname;
    }

    /**
     * The company's current platform subdomain, whether or not subdomain sites are enabled.
     */
    public function subdomainHost(Tenant $tenant): ?string
    {
        return $this->subdomains->current($tenant)?->hostname;
    }

    /**
     * The host the canonical address lives on: the custom domain, else the subdomain when subdomain sites are enabled.
     * Null means the canonical address is the standard one, on the main domain.
     */
    public function canonicalHost(Tenant $tenant): ?string
    {
        return $this->customHost($tenant) ?? ($this->subdomainSitesEnabled() ? $this->subdomainHost($tenant) : null);
    }

    /**
     * Base of the canonical address, without a trailing slash: https://www.empresa.com.br or the standard address.
     */
    public function canonicalBase(Tenant $tenant): ?string
    {
        $host = $this->canonicalHost($tenant);

        return $host !== null ? $this->hostBase($host) : $this->home($tenant);
    }

    /**
     * Canonical address of a page: '' for the home, '/servicos' for the services page. The home of a host keeps its
     * trailing slash (https://www.empresa.com.br/).
     */
    public function canonical(Tenant $tenant, string $suffix = ''): ?string
    {
        $host = $this->canonicalHost($tenant);

        if ($host !== null) {
            return $this->hostBase($host).($suffix === '' ? '/' : $suffix);
        }

        $home = $this->home($tenant);

        return $home === null ? null : $home.$suffix;
    }

    /**
     * Absolute base of a hostname, with the public scheme: https://www.empresa.com.br.
     */
    public function hostBase(string $hostname): string
    {
        return config('platform.public_scheme', 'https').'://'.$hostname;
    }

    private function standard(Tenant $tenant, string $suffix): ?string
    {
        $path = $this->path($tenant);

        return $path === null ? null : config('platform.public_url').$path.$suffix;
    }
}
