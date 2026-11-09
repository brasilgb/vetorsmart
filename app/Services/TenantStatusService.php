<?php

namespace App\Services;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;

final class TenantStatusService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Activate or suspend a tenant. Suspended tenants lose access for every member.
     */
    public function change(Tenant $tenant, TenantStatus $status, User $actor): Tenant
    {
        if ($tenant->status === $status) {
            return $tenant;
        }

        $previousStatus = $tenant->status;
        $tenant->status = $status;
        $tenant->save();

        $this->auditLogger->record(
            $status === TenantStatus::Active ? 'tenant.activated' : 'tenant.suspended',
            $tenant,
            $tenant,
            ['from' => $previousStatus->value, 'to' => $status->value],
            $actor,
        );

        return $tenant;
    }
}
