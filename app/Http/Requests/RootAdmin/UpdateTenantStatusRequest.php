<?php

namespace App\Http\Requests\RootAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantStatusRequest extends FormRequest
{
    /**
     * Suspending a company requires an explicit confirmation from the operator.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,suspended'],
            'confirm' => ['accepted_if:status,suspended'],
        ];
    }
}
