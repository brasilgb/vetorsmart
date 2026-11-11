<?php

namespace App\Http\Requests\Tenant\Operations;

use App\Enums\RequestPriority;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequestRequest extends FormRequest
{
    /**
     * Customer and optional service must belong to the active tenant.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(TenantContext $context): array
    {
        $tenantId = $context->tenantId();

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'description' => ['required', 'string', 'max:2000'],
            'priority' => ['sometimes', Rule::enum(RequestPriority::class)],
        ];
    }
}
