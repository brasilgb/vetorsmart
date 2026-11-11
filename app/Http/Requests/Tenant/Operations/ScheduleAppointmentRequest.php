<?php

namespace App\Http\Requests\Tenant\Operations;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Booking input. Times arrive as "Y-m-d H:i" in the tenant's timezone and are stored as UTC instants.
 */
class ScheduleAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(TenantContext $context): array
    {
        return [
            'professional_id' => ['required', Rule::exists('professionals', 'id')->where('tenant_id', $context->tenantId())],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
        ];
    }

    public function startsAt(Tenant $tenant): CarbonImmutable
    {
        return $tenant->instantFromLocal((string) $this->input('starts_at'));
    }
}
