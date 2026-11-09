<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Base for jobs that act on one tenant.
 *
 * The tenant and the requesting user are passed explicitly when the job is
 * dispatched. Before processing, both are checked again: the tenant must still
 * be active and the user must still hold an active membership. If either check
 * fails, the job does nothing, so a stale queue entry cannot touch another
 * tenant's data or a suspended tenant.
 */
abstract class TenantScopedJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public int $tenantId,
        public int $userId,
    ) {}

    /**
     * Revalidate the tenant context and run the job inside it.
     */
    public function handle(TenantResolver $resolver, TenantContext $context): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null || ! $user->isActive()) {
            return;
        }

        $membership = $resolver->membershipFor($user, $this->tenantId);

        if ($membership === null) {
            return;
        }

        $context->runFor($membership, fn () => $this->handleForTenant($membership->tenant));
    }

    /**
     * Process the job for the revalidated tenant.
     */
    abstract protected function handleForTenant(Tenant $tenant): void;
}
