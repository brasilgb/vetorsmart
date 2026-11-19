<?php

namespace App\Http\Requests\Tenant\Operations;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The realized amount of a completed service, typed in reais ("1.234,56"). Empty means unknown. Used when completing
 * the service and when recording or correcting the amount later. Converted to integer cents, never to a float.
 */
class RealizedAmountRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'realized_amount' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $value = $this->input('realized_amount');

                if (is_string($value) && trim($value) !== '' && Money::parse($value) === null) {
                    $validator->errors()->add('realized_amount', 'Informe o valor em reais, por exemplo 1.250,00.');
                }
            },
        ];
    }

    /**
     * The amount in cents, or null when left empty (unknown).
     */
    public function cents(): ?int
    {
        $value = $this->validated('realized_amount');

        return is_string($value) && trim($value) !== '' ? Money::parse($value) : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['realized_amount' => 'valor realizado'];
    }
}
