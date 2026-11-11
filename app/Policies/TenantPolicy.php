<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

/**
 * Tenant-level permissions. Each check uses the membership bound to the request,
 * and never grants access to a tenant other than the active one.
 *
 * Owner: manages settings and every user.
 * Admin: manages settings and operators only; cannot grant owner or admin.
 * Operator: no administrative permissions.
 */
class TenantPolicy
{
    public function __construct(private TenantContext $context) {}

    /**
     * Determine whether the user may update the tenant's settings.
     */
    public function manageSettings(User $user, Tenant $tenant): bool
    {
        return $this->isActiveTenant($tenant) && $this->hasRoleIn([TenantRole::Owner, TenantRole::Admin]);
    }

    /**
     * Owners and admins configure the visitor assistant.
     */
    public function manageAssistant(User $user, Tenant $tenant): bool
    {
        return $this->isActiveTenant($tenant) && $this->hasRoleIn([TenantRole::Owner, TenantRole::Admin]);
    }

    /**
     * Any member may read the conversation inbox and take conversations; operators included.
     */
    public function handleAssistant(User $user, Tenant $tenant): bool
    {
        return $this->isActiveTenant($tenant) && $this->hasRoleIn([TenantRole::Owner, TenantRole::Admin, TenantRole::Operator]);
    }

    /**
     * Determine whether the user may list the tenant's users.
     */
    public function viewUsers(User $user, Tenant $tenant): bool
    {
        return $this->isActiveTenant($tenant) && $this->hasRoleIn([TenantRole::Owner, TenantRole::Admin]);
    }

    /**
     * Determine whether the user may create, update or deactivate the target membership.
     */
    public function manageUser(User $user, Tenant $tenant, TenantUser $target): bool
    {
        if (! $this->isActiveTenant($tenant) || $target->tenant_id !== $tenant->getKey()) {
            return false;
        }

        if ($this->hasRoleIn([TenantRole::Owner])) {
            return true;
        }

        return $this->hasRoleIn([TenantRole::Admin]) && $target->role === TenantRole::Operator;
    }

    /**
     * Determine whether the user may grant the given role inside the tenant.
     */
    public function assignRole(User $user, Tenant $tenant, TenantRole $role): bool
    {
        if (! $this->isActiveTenant($tenant)) {
            return false;
        }

        if ($this->hasRoleIn([TenantRole::Owner])) {
            return true;
        }

        return $this->hasRoleIn([TenantRole::Admin]) && $role === TenantRole::Operator;
    }

    /**
     * Determine whether the tenant is the one bound to the request.
     */
    private function isActiveTenant(Tenant $tenant): bool
    {
        return $this->context->tenantId() === $tenant->getKey();
    }

    /**
     * @param  list<TenantRole>  $roles
     */
    private function hasRoleIn(array $roles): bool
    {
        $membership = $this->context->membership();

        return $membership !== null && in_array($membership->role, $roles, true);
    }
}
