<?php

namespace App\Http\Requests\Tenant;

use App\Enums\BrandColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Each section of Meu Site is saved on its own. Fields outside the section are ignored.
 */
class UpdateWebsiteDraftRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', Rule::in(['appearance', 'content', 'services', 'seo'])],

            'accent_color' => ['required_if:section,appearance', Rule::enum(BrandColor::class)],
            'font' => ['required_if:section,appearance', Rule::in(['sans', 'serif'])],

            'headline' => ['nullable', 'string', 'max:160'],
            'about' => ['nullable', 'string', 'max:2000'],
            'differentials' => ['sometimes', 'array', 'max:6'],
            'differentials.*' => ['string', 'max:120'],
            'show_hours' => ['sometimes', 'boolean'],

            'service_ids' => ['sometimes', 'array', 'max:50'],
            'service_ids.*' => ['integer'],
            'show_professionals' => ['sometimes', 'boolean'],
            'professional_ids' => ['sometimes', 'array', 'max:50'],
            'professional_ids.*' => ['integer'],

            'seo' => ['sometimes', 'array'],
            'seo.title' => ['nullable', 'string', 'max:70'],
            'seo.description' => ['nullable', 'string', 'max:160'],
            'seo.indexable' => ['sometimes', 'boolean'],
        ];
    }
}
