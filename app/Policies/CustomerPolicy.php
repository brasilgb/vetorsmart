<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

/**
 * Operators register and update customers during service, so every member may write.
 * Deactivating a customer is reserved for owners and admins.
 */
class CustomerPolicy
{
    public function __construct(private TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->membership() !== null;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->context->owns($customer->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->context->membership() !== null;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->context->owns($customer->tenant_id);
    }

    public function deactivate(User $user, Customer $customer): bool
    {
        return $this->context->owns($customer->tenant_id)
            && $this->context->hasRoleIn([TenantRole::Owner, TenantRole::Admin]);
    }
}
