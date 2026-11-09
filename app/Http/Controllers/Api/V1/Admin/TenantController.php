<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TenantResource;
use App\Models\Tenant;
use App\Services\TenantStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    /**
     * List every tenant on the platform.
     */
    public function index(): AnonymousResourceCollection
    {
        return TenantResource::collection(
            Tenant::query()->with('segment')->orderBy('id')->paginate(20),
        );
    }

    /**
     * Show a tenant's administrative data. Platform authority does not
     * grant access to the tenant's own users or settings.
     */
    public function show(Tenant $tenant): TenantResource
    {
        return new TenantResource($tenant->loadMissing('segment'));
    }

    /**
     * Activate a tenant.
     */
    public function activate(Request $request, Tenant $tenant, TenantStatusService $statuses): TenantResource
    {
        return new TenantResource($statuses->change($tenant, TenantStatus::Active, $request->user())->loadMissing('segment'));
    }

    /**
     * Suspend a tenant.
     */
    public function suspend(Request $request, Tenant $tenant, TenantStatusService $statuses): TenantResource
    {
        return new TenantResource($statuses->change($tenant, TenantStatus::Suspended, $request->user())->loadMissing('segment'));
    }
}
