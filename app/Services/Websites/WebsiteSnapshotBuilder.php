<?php

namespace App\Services\Websites;

use App\Models\AssistantSetting;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSetting;

/**
 * Builds the content a visitor sees from real company data. Only active services and
 * professionals that the company selected are included; empty sections are left out.
 * The output is data only: no HTML is ever taken from the company.
 */
class WebsiteSnapshotBuilder
{
    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function build(Tenant $tenant, array $draft, string $templateLabel): array
    {
        $setting = TenantSetting::query()->forTenant($tenant)->first();

        $serviceIds = array_map('intval', (array) ($draft['service_ids'] ?? []));
        $professionalIds = array_map('intval', (array) ($draft['professional_ids'] ?? []));

        $services = $serviceIds === [] ? collect() : Service::query()
            ->forTenant($tenant)
            ->where('active', true)
            ->whereIn('id', $serviceIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $professionals = ! ($draft['show_professionals'] ?? false) || $professionalIds === [] ? collect() : Professional::query()
            ->forTenant($tenant)
            ->where('active', true)
            ->whereIn('id', $professionalIds)
            ->orderBy('name')
            ->get();

        $hours = ($draft['show_hours'] ?? false) ? ($setting?->business_hours) : null;
        $showPrices = (bool) ($draft['show_prices'] ?? true);
        $region = AssistantSetting::query()->forTenant($tenant)->value('service_region');

        return [
            'business_name' => $setting === null ? $tenant->name : $setting->business_name,
            'segment' => $tenant->segment?->name,
            'segment_slug' => $tenant->segment?->slug,
            'template_label' => $templateLabel,
            'headline' => $draft['headline'] ?? null,
            'about' => $draft['about'] ?? null,
            'differentials' => array_values(array_filter((array) ($draft['differentials'] ?? []), fn ($item): bool => is_string($item) && $item !== '')),
            'accent_color' => $draft['accent_color'] ?? '#2563EB',
            'font' => $draft['font'] ?? 'sans',
            'logo' => $this->image($draft['logo'] ?? null),
            'hero' => $this->image($draft['hero'] ?? null),
            'service_region' => is_string($region) && trim($region) !== '' ? trim($region) : null,
            'services' => $services->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => $service->duration_minutes,
                'price_label' => $showPrices ? $this->priceLabel($service) : null,
            ])->values()->all(),
            'professionals' => $professionals->map(fn (Professional $professional): array => ['name' => $professional->name])->values()->all(),
            'hours' => is_array($hours) ? $hours : null,
            'contact' => [
                'phone' => $tenant->phone,
                'whatsapp' => $setting?->whatsapp,
                'email' => $tenant->email,
                'address' => $setting?->address,
                'city' => $setting?->city,
                'state' => $setting?->state,
                'google_review_url' => $setting->settings['google_review_url'] ?? null,
            ],
            'seo' => [
                'title' => $draft['seo']['title'] ?? null,
                'description' => $draft['seo']['description'] ?? null,
                'indexable' => (bool) ($draft['seo']['indexable'] ?? true),
            ],
        ];
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
