<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceCategoryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('service_categories', 'name')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
