<?php

namespace App\Http\Requests\Api\V1\Professionals;

use Illuminate\Foundation\Http\FormRequest;

class SyncProfessionalAvailabilityRequest extends FormRequest
{
    /**
     * The payload replaces every working window of the professional.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'windows' => ['present', 'array', 'max:70'],
            'windows.*.weekday' => ['required', 'integer', 'between:0,6'],
            'windows.*.start_time' => ['required', 'date_format:H:i'],
            'windows.*.end_time' => ['required', 'date_format:H:i', 'after:windows.*.start_time'],
        ];
    }
}
