<?php

namespace App\Http\Requests\Tenant\Operations;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfessionalBlockRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(TenantContext $context): array
    {
        return [
            'professional_id' => ['required', Rule::exists('professionals', 'id')->where('tenant_id', $context->tenantId())],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'ends_at' => ['required', 'date_format:Y-m-d H:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function startsAt(Tenant $tenant): CarbonImmutable
    {
        return $tenant->instantFromLocal((string) $this->input('starts_at'));
    }

    public function endsAt(Tenant $tenant): CarbonImmutable
    {
        return $tenant->instantFromLocal((string) $this->input('ends_at'));
    }
}
