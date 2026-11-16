<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An optional custom domain. The official address /{segment}/{company} needs no domain, so platform subdomains are
 * no longer created.
 */
class StoreWebsiteDomainRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:253'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hostname.required' => 'Informe o domínio próprio, como www.suaempresa.com.br.',
        ];
    }
}
