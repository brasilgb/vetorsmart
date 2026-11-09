<?php

namespace App\Http\Requests\Api\V1\Professionals;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfessionalRequest extends FormRequest
{
    /**
     * Only the name is required. The professional does not need a login.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
