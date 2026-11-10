<?php

namespace App\Services\Websites;

use App\Enums\BrandColor;
use App\Enums\DomainStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantWebsite;

/**
 * Shapes Meu Site data for the administrative screen. Holds no business rules.
 */
class WebsitePresenter
{
    public function __construct(private WebsiteDomainService $domains) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant, TenantWebsite $website): array
    {
        $publishedAt = $website->published_at;
        $updatedAt = $website->updated_at;

        return [
            'name' => $website->name,
            'status' => $website->status->value,
            'is_live' => $website->isLive(),
            'published_at' => $publishedAt?->format('d/m/Y H:i'),
            'updated_at' => $updatedAt?->format('d/m/Y H:i'),
            'public_url' => $this->publicUrl($tenant),
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
     * @return list<array<string, mixed>>
     */
    public function domains(Tenant $tenant): array
    {
        $rows = [];

        $records = TenantDomain::query()
            ->forTenant($tenant)
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
                'verified_at' => $verifiedAt?->format('d/m/Y H:i'),
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
     * Address visitors use: the primary verified domain, else the first verified one.
     */
    public function publicUrl(Tenant $tenant): ?string
    {
        $domain = TenantDomain::query()
            ->forTenant($tenant)
            ->where('status', DomainStatus::Verified)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        if ($domain === null) {
            return null;
        }

        return config('platform.public_scheme', 'https').'://'.$domain->hostname;
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
