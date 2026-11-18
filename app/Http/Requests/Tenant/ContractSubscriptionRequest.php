<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contracting (or paying the next period): the payment method and, for the billet, the company's CPF or CNPJ,
 * which Efí requires on the billet.
 */
class ContractSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'document' => ['nullable', 'string', 'max:20', 'regex:/^[\d.\-\/\s]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.required' => 'Escolha a forma de pagamento.',
            'method.enum' => 'Forma de pagamento inválida.',
            'document.regex' => 'Informe o CPF ou CNPJ só com números, pontos, traços e barra.',
        ];
    }
}
