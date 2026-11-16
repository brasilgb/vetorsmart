<?php

namespace App\Services\Websites;

use App\Enums\DomainStatus;
use App\Enums\TenantStatus;
use App\Enums\WebsiteStatus;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantWebsite;
use App\Models\WebsiteVersion;
use Illuminate\Support\Facades\Cache;

/**
 * Identifies a public company site. The official address is /{segment}/{company} on the platform domain; a site
 * is live only when the company is active, its segment is active and its site is published. Anything else returns
 * null, which callers answer with a generic not-found. Published content is cached by version, which is immutable,
 * so a new publication naturally replaces the cached copy.
 */
class WebsiteResolver
{
    public function __construct(private HostnameNormalizer $normalizer, private ReservedPaths $reserved) {}

    /**
     * The live site at /{segment}/{company}. Both slugs come from the URL only; nothing from the session or the payload.
     *
     * @return array{tenant: Tenant, website: TenantWebsite, content: array<string, mixed>}|null
     */
    public function resolvePath(string $segmentSlug, string $companySlug): ?array
    {
        if ($this->reserved->isReserved($segmentSlug)) {
            return null;
        }

        $segment = Segment::query()->where('slug', $segmentSlug)->where('active', true)->first();

        // Slugs are compared exactly: the database collation would otherwise accept other casings as the same address.
        if ($segment === null || $segment->slug !== $segmentSlug) {
            return null;
        }

        $tenant = Tenant::query()
            ->where('segment_id', $segment->id)
            ->where('slug', $companySlug)
            ->where('status', TenantStatus::Active)
            ->first();

        if ($tenant === null || $tenant->slug !== $companySlug) {
            return null;
        }

        $tenant->setRelation('segment', $segment);

        return $this->liveSite($tenant);
    }

    /**
     * The live site behind a verified hostname: a company's own domain, which serves the site, or an earlier platform
     * subdomain, which only redirects. A company's site is live under the same rules as at its standard address.
     *
     * @return array{tenant: Tenant, website: TenantWebsite, content: array<string, mixed>, domain: TenantDomain}|null
     */
    public function resolve(?string $rawHost): ?array
    {
        $hostname = $this->normalizer->normalize($rawHost);

        if ($hostname === null || $hostname === (string) config('platform.central_host')) {
            return null;
        }

        $domain = TenantDomain::query()
            ->with('tenant.segment')
            ->where('hostname', $hostname)
            ->where('status', DomainStatus::Verified)
            ->first();

        if ($domain === null || $domain->tenant === null || ! $domain->tenant->isActive()) {
            return null;
        }

        $segment = $domain->tenant->segment;

        if ($segment === null || ! $segment->active || $this->reserved->isReserved($segment->slug)) {
            return null;
        }

        $site = $this->liveSite($domain->tenant);

        return $site === null ? null : [...$site, 'domain' => $domain];
    }

    /**
     * Whether the official address of this company shows its site right now.
     */
    public function isLive(Tenant $tenant): bool
    {
        $tenant->loadMissing('segment');
        $segment = $tenant->segment;

        if (! $tenant->isActive() || $segment === null || ! $segment->active || $this->reserved->isReserved($segment->slug)) {
            return false;
        }

        return $this->liveSite($tenant) !== null;
    }

    /**
     * Every live site whose company and segment are active, for the central sitemap.
     *
     * @return list<array{tenant: Tenant, website: TenantWebsite, content: array<string, mixed>}>
     */
    public function liveSites(): array
    {
        $tenants = Tenant::query()
            ->with('segment')
            ->where('status', TenantStatus::Active)
            ->whereHas('segment', fn ($query) => $query->where('active', true))
            ->whereIn('id', TenantWebsite::query()->where('status', WebsiteStatus::Published)->whereNotNull('published_version_id')->select('tenant_id'))
            ->orderBy('id')
            ->get();

        $sites = [];

        foreach ($tenants as $tenant) {
            $segment = $tenant->segment;

            if ($segment === null || $this->reserved->isReserved($segment->slug)) {
                continue;
            }

            $site = $this->liveSite($tenant);

            if ($site !== null) {
                $sites[] = $site;
            }
        }

        return $sites;
    }

    /**
     * @return array{tenant: Tenant, website: TenantWebsite, content: array<string, mixed>}|null
     */
    private function liveSite(Tenant $tenant): ?array
    {
        $website = TenantWebsite::query()->forTenant($tenant->id)->first();

        if ($website === null || $website->status !== WebsiteStatus::Published || $website->published_version_id === null) {
            return null;
        }

        $content = $this->publishedContent($website);

        if ($content === null) {
            return null;
        }

        return ['tenant' => $tenant, 'website' => $website, 'content' => $content];
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
