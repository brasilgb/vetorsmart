<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Enums\PriceType;
use App\Models\ServiceCategory;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    /**
     * Partial update. The category must be an active category of the same tenant.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'service_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists(ServiceCategory::class, 'id')->where('tenant_id', $tenantId)->where('active', true)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'price_type' => ['sometimes', Rule::enum(PriceType::class)],
            'requires_evaluation' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
