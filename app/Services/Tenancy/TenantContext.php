<?php

namespace App\Services\Tenancy;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Holds the tenant membership active for the current request or job.
 *
 * Registered as a scoped binding, so each request and each queued job starts
 * without a previous tenant. runFor() also restores the prior context, so
 * nested or sequential executions never leak a tenant into another.
 */
final class TenantContext
{
    private ?TenantUser $membership = null;

    /**
     * Set the active membership. Its tenant is loaded to avoid extra queries.
     */
    public function set(?TenantUser $membership): void
    {
        $this->membership = $membership;

        $membership?->loadMissing('tenant');
    }

    /**
     * Forget the active membership.
     */
    public function forget(): void
    {
        $this->membership = null;
    }

    /**
     * Get the active membership, if any.
     */
    public function membership(): ?TenantUser
    {
        return $this->membership;
    }

    /**
     * Get the active tenant, if any.
     */
    public function tenant(): ?Tenant
    {
        return $this->membership?->tenant;
    }

    /**
     * Get the active tenant ID, if any.
     */
    public function tenantId(): ?int
    {
        return $this->membership?->tenant_id;
    }

    /**
     * Determine whether the active membership has one of the given roles.
     *
     * @param  list<TenantRole>  $roles
     */
    public function hasRoleIn(array $roles): bool
    {
        $membership = $this->membership;

        return $membership !== null && in_array($membership->role, $roles, true);
    }

    /**
     * Determine whether the given tenant is the one bound to the context.
     */
    public function owns(int $tenantId): bool
    {
        return $this->tenantId() === $tenantId;
    }

    /**
     * Get the active tenant or fail when no tenant is active.
     *
     * @throws AuthorizationException
     */
    public function requireTenant(): Tenant
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            throw new AuthorizationException('Nenhuma empresa ativa para esta operação.');
        }

        return $tenant;
    }

    /**
     * Get the active membership or fail when no tenant is active.
     *
     * @throws AuthorizationException
     */
    public function require(): TenantUser
    {
        if ($this->membership === null) {
            throw new AuthorizationException('Nenhuma empresa ativa para esta operação.');
        }

        return $this->membership;
    }

    /**
     * Run the callback with the given membership, then restore the previous context.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runFor(TenantUser $membership, Closure $callback): mixed
    {
        $previous = $this->membership;

        $this->set($membership);

        try {
            return $callback();
        } finally {
            $this->membership = $previous;
        }
    }
}
