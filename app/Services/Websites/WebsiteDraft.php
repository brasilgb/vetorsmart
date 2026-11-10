<?php

namespace App\Services\Websites;

/**
 * Default content of a new draft. Nothing here is invented: empty means "not shown".
 */
class WebsiteDraft
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'headline' => null,
            'about' => null,
            'differentials' => [],
            'accent_color' => '#2563EB',
            'font' => 'sans',
            'service_ids' => [],
            'professional_ids' => [],
            'show_professionals' => false,
            'show_hours' => false,
            'seo' => ['title' => null, 'description' => null, 'indexable' => true],
        ];
    }
}
