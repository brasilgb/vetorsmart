<?php

namespace App\Http\Requests\RootAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A package of additional AI credits. The price arrives in reais and is stored in cents.
 */
class SaveAiCreditPackageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'credits' => ['required', 'integer', 'min:1', 'max:100000000'],
            'price' => ['required', 'numeric', 'min:1', 'max:99999'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credits.min' => 'Informe ao menos 1 crédito.',
            'price.min' => 'Informe um valor maior que zero.',
        ];
    }
}
