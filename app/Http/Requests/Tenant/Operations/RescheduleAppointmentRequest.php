<?php

namespace App\Http\Requests\Tenant\Operations;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
        ];
    }

    public function startsAt(Tenant $tenant): CarbonImmutable
    {
        return $tenant->instantFromLocal((string) $this->input('starts_at'));
    }
}
