<?php

namespace App\Services\Websites;

use App\Enums\DomainStatus;
use App\Enums\WebsiteStatus;
use App\Models\TenantDomain;
use App\Models\TenantWebsite;
use App\Models\WebsiteVersion;
use Illuminate\Support\Facades\Cache;

/**
 * Identifies the public site behind a hostname. Only a verified domain of an active
 * company whose site is published resolves; anything else returns null, which callers
 * must answer with a generic not-found. Published content is cached by version, which
 * is immutable, so a new publication naturally replaces the cached copy.
 */
class WebsiteResolver
{
    public function __construct(private HostnameNormalizer $normalizer) {}

    /**
     * @return array{domain: TenantDomain, website: TenantWebsite, content: array<string, mixed>, primary: ?TenantDomain}|null
     */
    public function resolve(?string $rawHost): ?array
    {
        $hostname = $this->normalizer->normalize($rawHost);

        if ($hostname === null || $hostname === (string) config('platform.central_host')) {
            return null;
        }

        $domain = TenantDomain::query()
            ->with('tenant')
            ->where('hostname', $hostname)
            ->where('status', DomainStatus::Verified)
            ->first();

        if ($domain === null || $domain->tenant === null || ! $domain->tenant->isActive()) {
            return null;
        }

        $website = TenantWebsite::query()->forTenant($domain->tenant_id)->first();

        if ($website === null || $website->status !== WebsiteStatus::Published || $website->published_version_id === null) {
            return null;
        }

        $content = $this->publishedContent($website);

        if ($content === null) {
            return null;
        }

        $primary = TenantDomain::query()
            ->forTenant($domain->tenant_id)
            ->where('is_primary', true)
            ->where('status', DomainStatus::Verified)
            ->first();

        return ['domain' => $domain, 'website' => $website, 'content' => $content, 'primary' => $primary];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publishedContent(TenantWebsite $website): ?array
    {
        $versionId = (int) $website->published_version_id;

        return Cache::remember(
            'site:tenant:'.$website->tenant_id.':version:'.$versionId,
            3600,
            fn (): ?array => WebsiteVersion::query()->forTenant($website->tenant_id)->whereKey($versionId)->first()?->content,
        );
    }
}
