<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

/**
 * Operators receive and move requests during service, so every member may work on them.
 * Each ability also requires the request to belong to the active tenant.
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
}
