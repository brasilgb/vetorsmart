<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ConfirmationPolicy;
use App\Enums\ServiceMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'state' => ['sometimes', 'nullable', 'string', 'size:2'],
            'locale' => ['sometimes', 'string', 'in:pt_BR,en'],
            'service_modes' => ['sometimes', 'array', 'min:1', 'max:3'],
            'service_modes.*' => ['required', Rule::enum(ServiceMode::class), 'distinct'],
            'welcome_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'confirmation_policy' => ['sometimes', Rule::enum(ConfirmationPolicy::class)],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
