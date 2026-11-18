<?php

namespace App\Http\Requests\RootAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Commercial parameters of the main plan. Amounts arrive in reais (e.g. 149.90) and are stored in cents. An empty
 * price keeps contracting closed (values not approved yet).
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
            'interval_months' => ['required', 'integer', 'in:1,3,6,12'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'included_users' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'extra_user_price' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'ai_messages_per_month' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'promotional_price' => ['nullable', 'numeric', 'min:1', 'max:99999', 'required_with:promotional_until'],
            'promotional_until' => ['nullable', 'date', 'after_or_equal:today', 'required_with:promotional_price'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.min' => 'Informe um valor maior que zero ou deixe em branco.',
            'interval_months.in' => 'Use 1, 3, 6 ou 12 meses.',
            'promotional_price.required_with' => 'Informe o valor promocional junto com a data final.',
            'promotional_until.required_with' => 'Informe até quando vale o valor promocional.',
            'promotional_until.after_or_equal' => 'A data final da promoção não pode estar no passado.',
        ];
    }
}
