<?php

namespace App\Http\Requests\Api\V1\Professionals;

use Illuminate\Foundation\Http\FormRequest;

class SyncProfessionalServicesRequest extends FormRequest
{
    /**
     * The payload replaces the whole set of services of the professional.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'service_ids' => ['present', 'array', 'max:200'],
            'service_ids.*' => ['integer', 'distinct'],
        ];
    }
}
