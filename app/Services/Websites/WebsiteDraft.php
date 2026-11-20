<?php

namespace App\Services\Websites;

/**
 * Default content of a new draft. Nothing here is invented: empty means "not shown".
 *
 * Keys added with the guided editor (an older draft simply lacks them and gets these defaults when published):
 * - service_details: per published service, an optional site description, photo and whether its price is public;
 * - about_photo, hero_alt, trust_items (certifications, years in business, reviews: only with an identifiable source);
 * - base_municipality_id, served_municipality_ids, service_mode: where the company is and where it serves;
 * - show_phone, show_whatsapp, show_email, show_address: what of the company's contacts the site shows;
 * - palette: one of SegmentTheme::PALETTES; accent_color stays the main color.
 */
class WebsiteDraft
{
    public const int MAX_SERVED_MUNICIPALITIES = 60;

    /** @var list<string> */
    public const array SERVICE_MODES = ['at_company', 'at_customer', 'both'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'headline' => null,
            'about' => null,
            'differentials' => [],
            'trust_items' => [],
            'accent_color' => '#2563EB',
            'palette' => null,
            'font' => 'sans',
            'service_ids' => [],
            'service_details' => [],
            'professional_ids' => [],
            'show_professionals' => false,
            'show_hours' => false,
            'show_prices' => true,
            'show_phone' => true,
            'show_whatsapp' => true,
            'show_email' => true,
            'show_address' => false,
            'logo' => null,
            'hero' => null,
            'hero_alt' => null,
            'about_photo' => null,
            'base_municipality_id' => null,
            'served_municipality_ids' => [],
            'service_mode' => null,
            'seo' => ['title' => null, 'description' => null, 'indexable' => true],
        ];
    }
}
