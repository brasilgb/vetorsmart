<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

/**
 * Operators receive and move requests during service, so every member may work on them. Choosing the responsible and
 * correcting a recorded amount are for owners and admins. Each ability also requires the request to belong to the
 * active tenant.
 */
class ServiceRequestPolicy
{
    public function __construct(private TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->membership() !== null;
    }

    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->context->owns($serviceRequest->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->context->membership() !== null;
    }

    public function manage(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->context->owns($serviceRequest->tenant_id);
    }

    /**
     * Choose or change the member responsible for a request: owners and admins.
     */
    public function assign(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->context->owns($serviceRequest->tenant_id) && $this->context->hasRoleIn([TenantRole::Owner, TenantRole::Admin]);
    }

    /**
     * Inform the realized amount of a completed service. Whoever works the request records it the first time;
     * correcting an amount already recorded is for owners and admins.
     */
    public function recordAmount(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->context->owns($serviceRequest->tenant_id)
            && ($serviceRequest->realized_amount_cents === null || $this->context->hasRoleIn([TenantRole::Owner, TenantRole::Admin]));
    }
}
