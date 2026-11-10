<?php

namespace App\Services\Tenancy;

use App\Enums\MembershipStatus;
use App\Enums\TenantStatus;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves which tenant a user may act on. Only memberships that are active,
 * for active tenants, are ever considered; the request input is never trusted.
 */
final class TenantResolver
{
    /**
     * Resolve the membership to use: the preferred tenant when the user still
     * has access to it, otherwise the user's first accessible tenant.
     */
    public function resolve(User $user, ?int $preferredTenantId = null): ?TenantUser
    {
        if ($preferredTenantId !== null) {
            $preferred = $this->membershipFor($user, $preferredTenantId);

            if ($preferred !== null) {
                return $preferred;
            }
        }

        return $this->accessibleMemberships($user)->orderBy('id')->first();
    }

    /**
     * List every tenant the user may act on, ordered for the tenant switcher.
     *
     * @return Collection<int, TenantUser>
     */
    public function accessibleFor(User $user): Collection
    {
        return $this->accessibleMemberships($user)->orderBy('id')->get();
    }

    /**
     * Get the membership linking the user to the tenant, when it grants access.
     */
    public function membershipFor(User $user, int $tenantId): ?TenantUser
    {
        return $this->accessibleMemberships($user)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * @return Builder<TenantUser>
     */
    private function accessibleMemberships(User $user): Builder
    {
        return TenantUser::query()
            ->with('tenant')
            ->where('user_id', $user->getKey())
            ->where('status', MembershipStatus::Active)
            ->whereHas('tenant', fn (Builder $query) => $query->where('status', TenantStatus::Active));
    }
}
