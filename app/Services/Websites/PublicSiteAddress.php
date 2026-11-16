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
 * may also serve the same site on its own verified domain (https://www.empresa.com.br). Both answer, but there is a
 * single canonical address per company so search engines never see two indexable copies: the custom domain when the
 * company has one verified, otherwise the standard address.
 */
class PublicSiteAddress
{
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
     * The company's own verified domain that serves its site: the one marked as primary, else the oldest verified.
     * Platform subdomains never serve a site, they only redirect.
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
     * Base of the canonical address, without a trailing slash: https://www.empresa.com.br or the standard address.
     */
    public function canonicalBase(Tenant $tenant): ?string
    {
        $custom = $this->customHost($tenant);

        if ($custom !== null) {
            return $this->hostBase($custom);
        }

        return $this->home($tenant);
    }

    /**
     * Canonical address of a page: '' for the home, '/servicos' for the services page. The home of a custom domain
     * keeps its trailing slash (https://www.empresa.com.br/).
     */
    public function canonical(Tenant $tenant, string $suffix = ''): ?string
    {
        $base = $this->canonicalBase($tenant);

        if ($base === null) {
            return null;
        }

        return $suffix === '' && $this->customHost($tenant) !== null ? $base.'/' : $base.$suffix;
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
