<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\BrandColor;
use App\Enums\ConfirmationPolicy;
use App\Enums\ServiceMode;
use App\Models\Segment;
use App\Services\BusinessHoursRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTenantSettingsRequest extends FormRequest
{
    /**
     * Tenant ownership is never read from the payload; the tenant comes from the request context.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'timezone' => ['sometimes', 'string', 'timezone:all', 'max:64'],
            'segment_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Segment::class, 'id')->where('active', true)],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'state' => ['sometimes', 'nullable', 'string', 'size:2'],
            'locale' => ['sometimes', 'string', 'in:pt_BR,en'],
            'brand_color' => ['sometimes', Rule::enum(BrandColor::class)],
            'business_hours' => ['sometimes', 'nullable', 'array'],
            'service_modes' => ['sometimes', 'array', 'min:1', 'max:3'],
            'service_modes.*' => ['required', Rule::enum(ServiceMode::class), 'distinct'],
            'welcome_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'confirmation_policy' => ['sometimes', Rule::enum(ConfirmationPolicy::class)],
            'settings' => ['sometimes', 'array'],
            'settings.currency' => ['sometimes', 'string', 'in:BRL'],
            'settings.date_format' => ['sometimes', 'string', 'in:d/m/Y,Y-m-d'],
        ];
    }

    /**
     * Opening hours need structural rules that a plain rule set cannot express.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hours = $this->input('business_hours');

            if (! is_array($hours)) {
                return;
            }

            foreach ((new BusinessHoursRules)->errors($hours) as $message) {
                $validator->errors()->add('business_hours', $message);
            }
        });
    }
}
