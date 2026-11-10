<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\TenantRole;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages users inside a tenant. Callers must already have checked permissions
 * and that the membership belongs to the tenant.
 */
class TenantUserService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Create a user and attach them to the tenant.
     */
    public function create(Tenant $tenant, string $name, string $email, string $password, TenantRole $role, User $actor): TenantUser
    {
        return DB::transaction(function () use ($tenant, $name, $email, $password, $role, $actor): TenantUser {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'status' => UserStatus::Active,
            ]);

            $membership = TenantUser::create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'role' => $role,
                'status' => MembershipStatus::Active,
            ]);

            $this->auditLogger->record('tenant_user.created', $tenant, $membership, [
                'user_id' => $user->getKey(),
                'role' => $role->value,
            ], $actor);

            return $membership;
        });
    }

    /**
     * Update the user's name and/or role within the tenant.
     *
     * @param  array{name?: string, role?: TenantRole}  $data
     */
    public function update(TenantUser $membership, array $data, User $actor): TenantUser
    {
        return DB::transaction(function () use ($membership, $data, $actor): TenantUser {
            $membership->loadMissing(['tenant', 'user']);

            if (isset($data['role']) && $data['role'] !== $membership->role) {
                $this->ensureOwnerRemains($membership);

                $previousRole = $membership->role;
                $membership->role = $data['role'];
                $membership->save();

                $this->auditLogger->record('tenant_user.role_changed', $membership->tenant, $membership, [
                    'user_id' => $membership->user_id,
                    'from' => $previousRole->value,
                    'to' => $data['role']->value,
                ], $actor);
            }

            if (isset($data['name']) && $data['name'] !== $membership->user->name) {
                $membership->user->update(['name' => $data['name']]);

                $this->auditLogger->record('tenant_user.updated', $membership->tenant, $membership, [
                    'user_id' => $membership->user_id,
                    'fields' => ['name'],
                ], $actor);
            }

            return $membership;
        });
    }

    /**
     * Reactivate a membership, restoring the user's access to this company.
     */
    public function activate(TenantUser $membership, User $actor): TenantUser
    {
        if ($membership->isActive()) {
            return $membership;
        }

        $membership->status = MembershipStatus::Active;
        $membership->save();

        $this->auditLogger->record('tenant_user.activated', $membership->tenant, $membership, [
            'user_id' => $membership->user_id,
        ], $actor);

        return $membership;
    }

    /**
     * Deactivate the membership. The user account itself is kept.
     */
    public function deactivate(TenantUser $membership, User $actor): TenantUser
    {
        return DB::transaction(function () use ($membership, $actor): TenantUser {
            $membership->loadMissing('tenant');

            if ($membership->isActive()) {
                $this->ensureOwnerRemains($membership);

                $membership->status = MembershipStatus::Inactive;
                $membership->save();

                $this->auditLogger->record('tenant_user.deactivated', $membership->tenant, $membership, [
                    'user_id' => $membership->user_id,
                ], $actor);
            }

            return $membership;
        });
    }

    /**
     * Refuse any change that would leave the tenant without an active owner.
     *
     * @throws ValidationException
     */
    private function ensureOwnerRemains(TenantUser $membership): void
    {
        if ($membership->role !== TenantRole::Owner || ! $membership->isActive()) {
            return;
        }

        $otherActiveOwners = TenantUser::query()
            ->forTenant($membership->tenant_id)
            ->where('role', TenantRole::Owner)
            ->where('status', MembershipStatus::Active)
            ->whereKeyNot($membership->getKey())
            ->count();

        if ($otherActiveOwners === 0) {
            throw ValidationException::withMessages([
                'role' => 'A empresa precisa manter ao menos um proprietário ativo.',
            ]);
        }
    }
}
