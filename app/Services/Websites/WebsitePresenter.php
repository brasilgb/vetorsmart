<?php

namespace App\Services\Websites;

use App\Enums\BrandColor;
use App\Enums\DomainKind;
use App\Enums\DomainStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantWebsite;

/**
 * Shapes Meu Site data for the administrative screen. Holds no business rules.
 */
class WebsitePresenter
{
    public function __construct(
        private WebsiteDomainService $domains,
        private PublicSiteAddress $address,
        private ReservedPaths $reserved,
        private TenantSubdomainService $subdomains,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant, TenantWebsite $website): array
    {
        $publishedAt = $website->published_at;
        $updatedAt = $website->updated_at;
        $customHost = $this->address->customHost($tenant);

        return [
            'name' => $website->name,
            'status' => $website->status->value,
            'is_live' => $website->isLive(),
            'published_at' => $publishedAt === null ? null : $tenant->toLocal($publishedAt)->format('d/m/Y H:i'),
            'updated_at' => $updatedAt === null ? null : $tenant->toLocal($updatedAt)->format('d/m/Y H:i'),
            'public_url' => $this->address->home($tenant),
            'custom_url' => $customHost === null ? null : $this->address->hostBase($customHost).'/',
            'subdomain' => $this->subdomain($tenant),
            'canonical_url' => $this->address->canonical($tenant),
            'address_issue' => $this->addressIssue($tenant),
            'visible' => $website->isLive() && $this->addressIssue($tenant) === null,
            'version' => $website->published_version_id === null ? null : (int) $website->versions()->max('version'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function colors(): array
    {
        $colors = [];

        foreach (BrandColor::cases() as $color) {
            $colors[] = ['value' => $color->value, 'label' => $this->colorLabel($color)];
        }

        return $colors;
    }

    /**
     * The company's platform subdomain for the panel, or null when it has none yet. "serving" says whether it already
     * shows the site (subdomain sites enabled) or still redirects to the standard address.
     *
     * @return array{hostname: string, label: string, url: string, base_domain: string, serving: bool, previous: list<string>, changes_left: int}|null
     */
    public function subdomain(Tenant $tenant): ?array
    {
        $current = $this->subdomains->current($tenant);

        if ($current === null) {
            return null;
        }

        return [
            'hostname' => $current->hostname,
            'label' => $this->subdomains->labelOf($current),
            'url' => $this->address->hostBase($current->hostname).'/',
            'base_domain' => (string) config('platform.tenant_base_domain'),
            'serving' => $this->address->subdomainSitesEnabled(),
            'previous' => $this->subdomains->retiredHostnames($tenant),
            'changes_left' => max(0, (int) config('platform.max_subdomain_changes') - $this->subdomains->changesUsed($tenant)),
        ];
    }

    /**
     * The company's custom domains. Platform subdomains are shown apart (see subdomain()).
     *
     * @return list<array<string, mixed>>
     */
    public function domains(Tenant $tenant): array
    {
        $rows = [];

        $records = TenantDomain::query()
            ->forTenant($tenant)
            ->where('kind', DomainKind::Custom)
            ->orderByDesc('is_primary')
            ->orderBy('hostname')
            ->get();

        foreach ($records as $domain) {
            $verifiedAt = $domain->verified_at;

            $rows[] = [
                'id' => $domain->id,
                'hostname' => $domain->hostname,
                'kind' => $domain->kind->value,
                'status' => $domain->status->value,
                'is_primary' => $domain->is_primary,
                'verified_at' => $verifiedAt === null ? null : $tenant->toLocal($verifiedAt)->format('d/m/Y H:i'),
                'dns' => $domain->status === DomainStatus::Pending ? [
                    'type' => 'TXT',
                    'name' => '_vetorsmart-verify.'.$domain->hostname,
                    'value' => $this->domains->expectedRecord($domain),
                ] : null,
            ];
        }

        return $rows;
    }

    /**
     * Why the official address cannot show the site even when it is published, in words for the company, or null.
     */
    public function addressIssue(Tenant $tenant): ?string
    {
        $tenant->loadMissing('segment');
        $segment = $tenant->segment;

        return match (true) {
            ! $tenant->isActive() => 'A empresa está suspensa. O site fica fora do ar até a reativação.',
            $segment === null => 'A empresa ainda não tem segmento definido. O endereço público é criado quando o segmento for definido pelo suporte.',
            ! $segment->active || $this->reserved->isReserved($segment->slug) => 'O segmento da empresa está indisponível no momento. Fale com o suporte.',
            default => null,
        };
    }

    private function colorLabel(BrandColor $color): string
    {
        return match ($color) {
            BrandColor::Ink => 'Azul escuro',
            BrandColor::Petrol => 'Azul petróleo',
            BrandColor::Blue => 'Azul tecnológico',
            BrandColor::Cyan => 'Cyan',
        };
    }
}
