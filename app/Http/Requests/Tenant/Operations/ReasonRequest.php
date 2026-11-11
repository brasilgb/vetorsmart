<?php

namespace App\Http\Requests\Tenant\Operations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A free-text reason required for cancellations and closings. The history keeps it.
 */
class ReasonRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
