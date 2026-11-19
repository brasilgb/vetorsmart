<?php

namespace App\Http\Requests\Tenant\Operations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The member responsible for a request, or empty to leave it without one. Membership of the same company and activity
 * are checked by ServiceRequestService, never trusted from here.
 */
class AssignServiceRequestRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'assigned_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
