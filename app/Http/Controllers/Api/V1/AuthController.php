<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SwitchTenantRequest;
use App\Http\Resources\Api\V1\TenantUserResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Show the authenticated user.
     */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * List the tenants the authenticated user may act on.
     */
    public function tenants(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memberships = TenantUser::query()
            ->with('tenant')
            ->where('user_id', $user->getKey())
            ->where('status', MembershipStatus::Active)
            ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active))
            ->orderBy('id')
            ->get();

        return TenantUserResource::collection($memberships)->response();
    }

    /**
     * Change the active tenant. Only tenants the user is an active member of are accepted.
     */
    public function switchTenant(SwitchTenantRequest $request, TenantResolver $resolver): TenantUserResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $membership = $resolver->membershipFor($user, (int) $request->validated('tenant_id'));

        if ($membership === null) {
            return response()->json(['message' => 'Você não tem acesso a esta empresa.'], 403);
        }

        $request->session()->put('current_tenant_id', $membership->tenant_id);

        return new TenantUserResource($membership->loadMissing('tenant'));
    }

    /**
     * End the current session.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
