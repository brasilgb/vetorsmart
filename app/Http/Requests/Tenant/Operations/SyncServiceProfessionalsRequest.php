<?php

namespace App\Http\Requests\Tenant\Operations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Professionals enabled for a service. Each id is checked against the tenant in ServiceService,
 * so a foreign id is refused with a validation error.
 */
class SyncServiceProfessionalsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'professional_ids' => ['present', 'array', 'max:200'],
            'professional_ids.*' => ['integer'],
        ];
    }
}
