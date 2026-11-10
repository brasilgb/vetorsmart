<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebsiteDomainRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:subdomain,custom'],
            'hostname' => ['required_if:kind,custom', 'nullable', 'string', 'max:253'],
        ];
    }
}
