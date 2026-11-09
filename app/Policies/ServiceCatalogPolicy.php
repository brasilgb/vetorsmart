<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules for the catalog and the professionals.
 *
 * Every member can read. Owners and admins write. The record must belong to the
 * active tenant, which is checked on every ability.
 */
abstract class ServiceCatalogPolicy
{
    public function __construct(protected TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->membership() !== null;
    }

    public function view(User $user, Model $record): bool
    {
        return $this->context->owns($record->getAttribute('tenant_id'));
    }

    public function create(User $user): bool
    {
        return $this->canManage();
    }

    public function update(User $user, Model $record): bool
    {
        return $this->context->owns($record->getAttribute('tenant_id')) && $this->canManage();
    }

    public function deactivate(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }

    /**
     * @return bool
     */
    protected function canManage(): bool
    {
        return $this->context->hasRoleIn([TenantRole::Owner, TenantRole::Admin]);
    }
}
