<?php

namespace App\Http\Requests\RootAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Commercial parameters of the main plan. Amounts arrive in reais (e.g. 139.90) and are stored in cents. Each cycle
 * has its own total price; an empty cycle cannot be contracted. prices_approved marks the prices as approved by the
 * partners, which is required to issue charges in the production environment.
 */
class UpdatePlanRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['nullable', 'numeric', 'min:1', 'max:99999'],
            'semiannual_price' => ['nullable', 'numeric', 'min:1', 'max:999999'],
            'annual_price' => ['nullable', 'numeric', 'min:1', 'max:999999'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'included_users' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'extra_user_price' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'ai_monthly_credits' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'promotional_discount_percent' => ['required', 'integer', 'min:0', 'max:90'],
            'promotional_until' => ['nullable', 'date', 'after_or_equal:today'],
            'prices_approved' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.min' => 'Informe um valor maior que zero ou deixe em branco.',
            'semiannual_price.min' => 'Informe um valor maior que zero ou deixe em branco.',
            'annual_price.min' => 'Informe um valor maior que zero ou deixe em branco.',
            'promotional_discount_percent.max' => 'O desconto promocional vai até 90%.',
            'promotional_until.after_or_equal' => 'A data final da promoção não pode estar no passado.',
        ];
    }
}
