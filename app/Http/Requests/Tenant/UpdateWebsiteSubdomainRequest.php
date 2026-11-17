<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new name for the company's platform subdomain. Format, reserved names and availability are checked by
 * TenantSubdomainService, the single place that knows the rules.
 */
class UpdateWebsiteSubdomainRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subdomain' => ['required', 'string', 'max:63'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.required' => 'Informe o novo endereço, como suaempresa.',
            'subdomain.max' => 'Use no máximo 63 caracteres.',
        ];
    }
}
