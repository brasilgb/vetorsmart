<?php

namespace App\Http\Requests\Tenant\Operations;

use App\Enums\RequestPriority;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriageServiceRequestRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(TenantContext $context): array
    {
        return [
            'service_id' => ['required', Rule::exists('services', 'id')->where('tenant_id', $context->tenantId())],
            'priority' => ['sometimes', Rule::enum(RequestPriority::class)],
        ];
    }
}
