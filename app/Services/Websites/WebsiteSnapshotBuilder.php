<?php

namespace App\Services\Websites;

use App\Models\AssistantSetting;
use App\Models\Municipality;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Location\MunicipalityDirectory;

/**
 * Builds the content a visitor sees from real company data. Only active services and
 * professionals that the company selected are included, in the order it chose; empty sections are left out.
 * The output is data only: no HTML is ever taken from the company.
 */
class WebsiteSnapshotBuilder
{
    public function __construct(private MunicipalityDirectory $municipalities, private SeoSuggestion $seo) {}

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function build(Tenant $tenant, array $draft, string $templateLabel): array
    {
        $draft = [...WebsiteDraft::defaults(), ...$draft];
        $setting = TenantSetting::query()->forTenant($tenant)->first();

        $serviceIds = array_values(array_unique(array_map('intval', (array) $draft['service_ids'])));
        $professionalIds = array_map('intval', (array) $draft['professional_ids']);
        $details = is_array($draft['service_details']) ? $draft['service_details'] : [];

        $catalog = $serviceIds === [] ? collect() : Service::query()
            ->forTenant($tenant)
            ->where('active', true)
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        // The order chosen by the company in Meu Site.
        $services = collect($serviceIds)->map(fn (int $id): ?Service => $catalog->get($id))->filter()->values();

        $professionals = ! $draft['show_professionals'] || $professionalIds === [] ? collect() : Professional::query()
            ->forTenant($tenant)
            ->where('active', true)
            ->whereIn('id', $professionalIds)
            ->orderBy('name')
            ->get();

        $hours = $draft['show_hours'] ? ($setting?->business_hours) : null;
        $showPrices = (bool) $draft['show_prices'];
        $region = AssistantSetting::query()->forTenant($tenant)->value('service_region');
        $businessName = $setting === null ? $tenant->name : $setting->business_name;

        $base = $this->municipalities->find($draft['base_municipality_id'])
            ?? $this->municipalities->byName($setting?->city, $setting?->state);
        $served = array_values(array_filter(
            $this->municipalities->many(array_map('intval', (array) $draft['served_municipality_ids'])),
            fn (Municipality $municipality): bool => $base === null || $municipality->id !== $base->id,
        ));
        $served = array_slice($served, 0, WebsiteDraft::MAX_SERVED_MUNICIPALITIES);
        $mode = in_array($draft['service_mode'], WebsiteDraft::SERVICE_MODES, true) ? $draft['service_mode'] : null;

        $city = $base->name ?? $setting?->city;
        $state = $base->state ?? $setting?->state;
        $suggestion = $this->seo->suggest((string) $businessName, $tenant->segment?->name, array_values(array_map(fn (Service $service): string => $service->name, $services->all())), $city, $state, count($served));
        $palette = is_string($draft['palette']) && isset(SegmentTheme::PALETTES[$draft['palette']]) ? $draft['palette'] : null;

        return [
            'business_name' => $businessName,
            'segment' => $tenant->segment?->name,
            'segment_slug' => $tenant->segment?->slug,
            'template_label' => $templateLabel,
            'headline' => $this->text($draft['headline']),
            'about' => $this->text($draft['about']),
            'differentials' => array_values(array_filter((array) $draft['differentials'], fn ($item): bool => is_string($item) && trim($item) !== '')),
            'trust_items' => $this->trustItems($draft['trust_items']),
            'accent_color' => SegmentTheme::hex($draft['accent_color']) ?? ($palette !== null ? SegmentTheme::PALETTES[$palette]['accent'] : '#2563EB'),
            'palette' => $palette,
            'font' => $draft['font'] === 'serif' ? 'serif' : 'sans',
            'logo' => $this->image($draft['logo']),
            'hero' => $this->image($draft['hero']),
            'hero_alt' => $this->text($draft['hero_alt']),
            'about_photo' => $this->image($draft['about_photo']),
            'service_region' => is_string($region) && trim($region) !== '' ? trim($region) : null,
            'location' => [
                'base' => $base?->toPublic(),
                'served' => array_map(fn (Municipality $municipality): array => $municipality->toPublic(), $served),
                'mode' => $mode,
            ],
            'services' => $services->map(function (Service $service) use ($details, $showPrices): array {
                $detail = is_array($details[(string) $service->id] ?? $details[$service->id] ?? null) ? ($details[(string) $service->id] ?? $details[$service->id]) : [];
                $public = $showPrices && ($detail['price_public'] ?? true) !== false;

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $this->text($detail['description'] ?? null) ?? $service->description,
                    'duration_minutes' => $service->duration_minutes,
                    'price_label' => $public ? ($this->priceLabel($service) ?? 'Sob consulta') : 'Sob consulta',
                    'photo' => $this->image($detail['photo'] ?? null),
                ];
            })->values()->all(),
            'professionals' => $professionals->map(fn (Professional $professional): array => ['name' => $professional->name])->values()->all(),
            'hours' => is_array($hours) ? $hours : null,
            'contact' => [
                'phone' => $draft['show_phone'] ? $tenant->phone : null,
                'whatsapp' => $draft['show_whatsapp'] ? $setting?->whatsapp : null,
                'email' => $draft['show_email'] ? $tenant->email : null,
                'address' => $draft['show_address'] ? $setting?->address : null,
                'city' => $city,
                'state' => $state,
                'google_review_url' => $setting->settings['google_review_url'] ?? null,
            ],
            'seo' => [
                'title' => $this->text($draft['seo']['title'] ?? null) ?? $suggestion['title'],
                'description' => $this->text($draft['seo']['description'] ?? null) ?? $suggestion['description'],
                'indexable' => (bool) ($draft['seo']['indexable'] ?? true),
            ],
        ];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Certifications, years in business or reviews, shown only with the source the company identified.
     *
     * @return list<array{label: string, source: string, url: string|null}>
     */
    private function trustItems(mixed $items): array
    {
        $kept = [];

        foreach ((array) $items as $item) {
            $label = is_array($item) ? $this->text($item['label'] ?? null) : null;
            $source = is_array($item) ? $this->text($item['source'] ?? null) : null;
            $url = is_array($item) && is_string($item['url'] ?? null) && str_starts_with($item['url'], 'https://') ? $item['url'] : null;

            if ($label !== null && $source !== null) {
                $kept[] = ['label' => $label, 'source' => $source, 'url' => $url];
            }
        }

        return array_slice($kept, 0, 6);
    }

    /**
     * A stored image of the draft, kept only with its expected shape.
     *
     * @return array{file: string, width: int, height: int}|null
     */
    private function image(mixed $image): ?array
    {
        if (! is_array($image) || ! is_string($image['file'] ?? null) || preg_match('/^'.WebsiteMedia::FILE_PATTERN.'$/', $image['file']) !== 1) {
            return null;
        }

        return ['file' => $image['file'], 'width' => (int) ($image['width'] ?? 0), 'height' => (int) ($image['height'] ?? 0)];
    }

    private function priceLabel(Service $service): ?string
    {
        return match ($service->price_type->value) {
            'fixed' => $service->price === null ? null : 'R$ '.number_format((float) $service->price, 2, ',', '.'),
            'starting_at' => $service->price === null ? null : 'A partir de R$ '.number_format((float) $service->price, 2, ',', '.'),
            default => 'Sob consulta',
        };
    }
}
