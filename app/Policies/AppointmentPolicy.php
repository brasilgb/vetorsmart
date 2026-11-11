<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\ProfessionalBlock;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

/**
 * Agenda management (booking, rescheduling, cancelling and blocking) is open to every member,
 * as for the requests. Records must belong to the active tenant.
 */
class AppointmentPolicy
{
    public function __construct(private TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->membership() !== null;
    }

    public function manage(User $user, Appointment $appointment): bool
    {
        return $this->context->owns($appointment->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->context->membership() !== null;
    }

    public function deleteBlock(User $user, ProfessionalBlock $block): bool
    {
        return $this->context->owns($block->tenant_id);
    }
}
