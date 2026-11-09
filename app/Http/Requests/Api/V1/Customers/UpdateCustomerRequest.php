<?php

namespace App\Http\Requests\Api\V1\Customers;

use App\Enums\CustomerStatus;
use App\Enums\PersonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Partial update: every field is optional.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'person_type' => ['sometimes', Rule::enum(PersonType::class)],
            'document' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
        ];
    }
}
