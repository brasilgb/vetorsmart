<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTenantUserRequest;
use App\Http\Requests\Api\V1\UpdateTenantUserRequest;
use App\Http\Resources\Api\V1\TenantUserResource;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\Tenancy\TenantContext;
use App\Services\TenantUserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TenantUserController extends Controller
{
    /**
     * List the active tenant's users.
     */
    public function index(TenantContext $context): AnonymousResourceCollection
    {
        /** @var Tenant $tenant */
        $tenant = $context->tenant();

        Gate::authorize('viewUsers', $tenant);

        $memberships = TenantUser::query()
            ->forTenant($tenant)
            ->with(['user', 'tenant'])
            ->orderBy('id')
            ->paginate(20);

        return TenantUserResource::collection($memberships);
    }

    /**
     * Create a user inside the active tenant.
     */
    public function store(StoreTenantUserRequest $request, TenantContext $context, TenantUserService $users): TenantUserResource
    {
        /** @var Tenant $tenant */
        $tenant = $context->tenant();
        $role = TenantRole::from($request->string('role')->value());

        Gate::authorize('viewUsers', $tenant);
        Gate::authorize('assignRole', [$tenant, $role]);

        $membership = $users->create(
            tenant: $tenant,
            name: $request->string('name')->value(),
            email: $request->string('email')->value(),
            password: $request->string('password')->value(),
            role: $role,
            actor: $request->user(),
        );

        return new TenantUserResource($membership->loadMissing(['user', 'tenant']));
    }

    /**
     * Update a membership of the active tenant.
     */
    public function update(
        UpdateTenantUserRequest $request,
        int $membershipId,
        TenantContext $context,
        TenantUserService $users,
    ): TenantUserResource {
        /** @var Tenant $tenant */
        $tenant = $context->tenant();
        $membership = $this->findMembership($tenant, $membershipId);
        $data = $request->validated();

        Gate::authorize('manageUser', [$tenant, $membership]);

        if (isset($data['role'])) {
            $data['role'] = TenantRole::from($data['role']);

            Gate::authorize('assignRole', [$tenant, $data['role']]);
        }

        $membership = $users->update($membership, $data, $request->user());

        return new TenantUserResource($membership->loadMissing(['user', 'tenant']));
    }

    /**
     * Deactivate a membership of the active tenant.
     */
    public function destroy(Request $request, int $membershipId, TenantContext $context, TenantUserService $users): TenantUserResource
    {
        /** @var Tenant $tenant */
        $tenant = $context->tenant();
        $membership = $this->findMembership($tenant, $membershipId);

        Gate::authorize('manageUser', [$tenant, $membership]);

        $membership = $users->deactivate($membership, $request->user());

        return new TenantUserResource($membership->loadMissing(['user', 'tenant']));
    }

    /**
     * Find a membership only inside the given tenant. Anything else is a 404,
     * so the existence of records in other tenants is not revealed.
     */
    private function findMembership(Tenant $tenant, int $membershipId): TenantUser
    {
        return TenantUser::query()
            ->forTenant($tenant)
            ->whereKey($membershipId)
            ->firstOrFail();
    }
}
