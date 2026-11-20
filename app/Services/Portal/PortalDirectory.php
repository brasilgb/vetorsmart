<?php

namespace App\Services\Portal;

use App\Models\Municipality;
use App\Models\PortalListing;
use App\Models\Tenant;
use App\Models\TenantWebsite;
use App\Models\WebsiteVersion;
use App\Services\Location\MunicipalityDirectory;
use App\Services\Websites\WebsiteMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps the Regional Portal entry of a company in step with its published site and its consent. The entry is built
 * only from the published version, never from the draft, the settings screen or internal records, and holds no phone
 * or e-mail. A company that did not opt in, has no live site or lacks the minimum public information has no entry.
 */
class PortalDirectory
{
    /**
     * Rebuild or remove the company's entry. Called after a publication, an unpublication and a consent change.
     */
    public function sync(Tenant $tenant): ?PortalListing
    {
        $website = TenantWebsite::query()->forTenant($tenant)->first();
        $content = $website === null || ! $website->portal_listed || ! $website->isLive() ? null : $this->publishedContent($website);
        $entry = $content === null ? null : $this->entryFrom($content);

        if ($website === null || $entry === null) {
            PortalListing::query()->forTenant($tenant)->delete();

            return null;
        }

        $tenant->loadMissing('segment');
        $areas = $entry['areas'];
        unset($entry['areas']);

        return DB::transaction(function () use ($tenant, $website, $entry, $areas): PortalListing {
            $listing = PortalListing::query()->updateOrCreate(['tenant_id' => $tenant->getKey()], [
                ...$entry,
                'website_version_id' => (int) $website->published_version_id,
                'segment_id' => $tenant->segment_id,
                'segment_search' => PortalText::normalize($tenant->segment?->name),
            ]);

            // Municipalities served, from the published version only; the search reads them by municipality.
            DB::table('portal_listing_areas')->where('portal_listing_id', $listing->getKey())->delete();
            DB::table('portal_listing_areas')->insert(array_map(fn (int $municipality): array => [
                'portal_listing_id' => $listing->getKey(),
                'tenant_id' => $tenant->getKey(),
                'municipality_id' => $municipality,
            ], $areas));

            return $listing;
        });
    }

    /**
     * What keeps the company out of the portal, in words for the panel. Empty when it can appear.
     *
     * @return list<string>
     */
    public function issues(Tenant $tenant, TenantWebsite $website): array
    {
        $issues = [];

        if ($tenant->isHomolog()) {
            $issues[] = 'Empresas de teste (homologação) não aparecem no portal.';
        }

        if (! $tenant->isActive()) {
            $issues[] = 'A empresa não está ativa.';
        }

        if (! $website->isLive()) {
            return [...$issues, 'Publique o site: o portal mostra apenas o que está publicado.'];
        }

        $content = $this->publishedContent($website) ?? [];
        $contact = is_array($content['contact'] ?? null) ? $content['contact'] : [];

        if (trim((string) ($content['business_name'] ?? '')) === '') {
            $issues[] = 'Informe o nome comercial em Configurações e publique o site novamente.';
        }

        if (trim((string) ($contact['city'] ?? '')) === '' || PortalText::state($contact['state'] ?? null) === null) {
            $issues[] = 'Informe a cidade e a UF em Configurações e publique o site novamente.';
        }

        return $issues;
    }

    /**
     * The searchable fields of a published version, or null when it lacks the minimum public identification
     * (business name, city and state).
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>|null
     */
    public function entryFrom(array $content): ?array
    {
        $contact = is_array($content['contact'] ?? null) ? $content['contact'] : [];
        $name = trim((string) ($content['business_name'] ?? ''));
        $city = trim((string) ($contact['city'] ?? ''));
        $state = PortalText::state($contact['state'] ?? null);

        if ($name === '' || $city === '' || $state === null) {
            return null;
        }

        $services = [];

        foreach ((array) ($content['services'] ?? []) as $service) {
            if (is_array($service) && is_string($service['name'] ?? null) && trim($service['name']) !== '') {
                $services[] = ['id' => is_int($service['id'] ?? null) ? $service['id'] : null, 'name' => trim($service['name'])];
            }
        }

        $services = array_slice($services, 0, 50);
        $about = trim((string) ($content['about'] ?? ''));
        $headline = trim((string) ($content['headline'] ?? ''));
        $summary = $about !== '' ? Str::limit((string) preg_replace('/\s+/', ' ', $about), 220) : ($headline !== '' ? Str::limit($headline, 220) : null);
        $region = trim((string) ($content['service_region'] ?? ''));
        $location = is_array($content['location'] ?? null) ? $content['location'] : [];
        $directory = app(MunicipalityDirectory::class);
        $base = $directory->find(is_array($location['base'] ?? null) ? ($location['base']['id'] ?? null) : null) ?? $directory->byName($city, $state);
        $areas = array_values(array_unique(array_map(
            fn (Municipality $municipality): int => (int) $municipality->id,
            $directory->many(array_map(fn ($item): int => is_array($item) ? (int) ($item['id'] ?? 0) : 0, (array) ($location['served'] ?? []))),
        )));
        $areas = array_values(array_filter($areas, fn (int $id): bool => $base === null || $id !== (int) $base->id));
        // The listing shows the served municipalities as its region when there is no free-text region.
        if ($region === '' && $areas !== []) {
            $region = implode(', ', array_map(fn ($item): string => is_array($item) ? (string) ($item['name'] ?? '') : '', array_slice((array) ($location['served'] ?? []), 0, 12)));
        }
        $logo = is_array($content['logo'] ?? null) && is_string($content['logo']['file'] ?? null) && preg_match('/^'.WebsiteMedia::FILE_PATTERN.'$/', $content['logo']['file']) === 1
            ? ['file' => $content['logo']['file'], 'width' => (int) ($content['logo']['width'] ?? 0), 'height' => (int) ($content['logo']['height'] ?? 0)]
            : null;

        return [
            'business_name' => Str::limit($name, 157),
            'name_search' => Str::limit(PortalText::normalize($name), 157, ''),
            'city' => Str::limit($city, 117),
            'city_search' => Str::limit(PortalText::normalize($city), 117, ''),
            'state' => $state,
            'municipality_id' => $base?->id,
            'areas' => $areas,
            'region' => $region === '' ? null : Str::limit($region, 250),
            'region_search' => Str::limit(PortalText::normalize($region), 250, ''),
            'summary' => $summary,
            'services' => $services,
            'services_search' => implode(' | ', array_map(fn (array $service): string => PortalText::normalize($service['name']), $services)),
            'logo' => $logo,
            'completeness' => ($logo !== null ? 1 : 0) + ($summary !== null ? 1 : 0) + ($services !== [] ? 1 : 0),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publishedContent(TenantWebsite $website): ?array
    {
        if ($website->published_version_id === null) {
            return null;
        }

        return WebsiteVersion::query()->forTenant($website->tenant_id)->whereKey($website->published_version_id)->first()?->content;
    }
}
