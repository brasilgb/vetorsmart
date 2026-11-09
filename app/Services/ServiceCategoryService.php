<?php

namespace App\Services;

use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\User;

class ServiceCategoryService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Tenant $tenant, array $attributes, User $actor): ServiceCategory
    {
        $category = ServiceCategory::create([
            ...$attributes,
            'tenant_id' => $tenant->getKey(),
        ]);

        $this->auditLogger->record('service_category.created', $tenant, $category, [
            'fields' => array_keys($attributes),
        ], $actor);

        return $category;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ServiceCategory $category, array $attributes, User $actor): ServiceCategory
    {
        $category->fill($attributes);
        $changedFields = array_keys($category->getDirty());
        $category->save();

        if ($changedFields !== []) {
            $this->auditLogger->record('service_category.updated', $category->tenant, $category, [
                'fields' => $changedFields,
            ], $actor);
        }

        return $category;
    }

    /**
     * Deactivate a category. Services that already use it keep their link.
     */
    public function deactivate(ServiceCategory $category, User $actor): ServiceCategory
    {
        if (! $category->active) {
            return $category;
        }

        $category->active = false;
        $category->save();

        $this->auditLogger->record('service_category.deactivated', $category->tenant, $category, [], $actor);

        return $category;
    }
}
