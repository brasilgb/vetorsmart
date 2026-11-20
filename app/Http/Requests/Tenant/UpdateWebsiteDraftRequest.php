<?php

namespace App\Http\Requests\Tenant;

use App\Services\Websites\SegmentTheme;
use App\Services\Websites\WebsiteDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Each question of Meu Site is saved on its own. Fields outside the section are ignored.
 *
 * Sections of the guided editor: identity, photo, about, location, mode, services, contact, colors, seo. The earlier
 * sections "appearance" and "content" are still accepted, with the same fields as before.
 */
class UpdateWebsiteDraftRequest extends FormRequest
{
    public const array SECTIONS = ['identity', 'photo', 'about', 'location', 'mode', 'services', 'contact', 'colors', 'seo', 'appearance', 'content'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $color = ['regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'section' => ['required', Rule::in(self::SECTIONS)],

            'accent_color' => ['required_if:section,appearance', 'required_if:section,colors', ...$color],
            'font' => ['required_if:section,appearance', 'required_if:section,colors', Rule::in(['sans', 'serif'])],
            'palette' => ['nullable', Rule::in(array_keys(SegmentTheme::PALETTES))],

            'headline' => ['nullable', 'string', 'max:160'],
            'hero_alt' => ['nullable', 'string', 'max:160'],
            'about' => ['nullable', 'string', 'max:2000'],
            'differentials' => ['sometimes', 'array', 'max:6'],
            'differentials.*' => ['nullable', 'string', 'max:120'],
            'trust_items' => ['sometimes', 'array', 'max:6'],
            'trust_items.*.label' => ['nullable', 'string', 'max:120'],
            'trust_items.*.source' => ['nullable', 'required_with:trust_items.*.label', 'string', 'max:160'],
            'trust_items.*.url' => ['nullable', 'url:https', 'max:255'],
            'show_hours' => ['sometimes', 'boolean'],

            'base_municipality_id' => ['nullable', 'required_if:section,location', 'integer', Rule::exists('municipalities', 'id')],
            'served_municipality_ids' => ['sometimes', 'array', 'max:'.WebsiteDraft::MAX_SERVED_MUNICIPALITIES],
            'served_municipality_ids.*' => ['integer', 'distinct', Rule::exists('municipalities', 'id')],

            'service_mode' => ['nullable', 'required_if:section,mode', Rule::in(WebsiteDraft::SERVICE_MODES)],

            'service_ids' => ['sometimes', 'array', 'max:50'],
            'service_ids.*' => ['integer', 'distinct'],
            'service_details' => ['sometimes', 'array', 'max:50'],
            'service_details.*.description' => ['nullable', 'string', 'max:500'],
            'service_details.*.price_public' => ['sometimes', 'boolean'],
            'show_professionals' => ['sometimes', 'boolean'],
            'show_prices' => ['sometimes', 'boolean'],
            'professional_ids' => ['sometimes', 'array', 'max:50'],
            'professional_ids.*' => ['integer'],

            'show_phone' => ['sometimes', 'boolean'],
            'show_whatsapp' => ['sometimes', 'boolean'],
            'show_email' => ['sometimes', 'boolean'],
            'show_address' => ['sometimes', 'boolean'],

            'seo' => ['sometimes', 'array'],
            'seo.title' => ['nullable', 'string', 'max:70'],
            'seo.description' => ['nullable', 'string', 'max:160'],
            'seo.indexable' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accent_color.regex' => 'Escolha uma cor válida.',
            'base_municipality_id.required_if' => 'Escolha o município onde fica a empresa.',
            'base_municipality_id.exists' => 'Escolha um município da lista.',
            'served_municipality_ids.max' => 'Informe no máximo '.WebsiteDraft::MAX_SERVED_MUNICIPALITIES.' municípios atendidos.',
            'served_municipality_ids.*.exists' => 'Escolha municípios da lista.',
            'service_mode.required_if' => 'Escolha como o atendimento acontece.',
            'trust_items.*.source.required_with' => 'Informe a fonte desta informação (por exemplo, o órgão que emitiu a certificação).',
            'trust_items.*.url.url' => 'Use um endereço que comece com https://.',
        ];
    }
}
