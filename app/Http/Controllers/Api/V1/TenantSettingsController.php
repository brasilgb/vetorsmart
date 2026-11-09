<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTenantSettingsRequest;
use App\Http\Resources\Api\V1\TenantSettingResource;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantContext;
use App\Services\TenantSettingsService;
use Illuminate\Support\Facades\Gate;

class TenantSettingsController extends Controller
{
    /**
     * Show the active tenant's settings.
     */
    public function show(TenantContext $context): TenantSettingResource
    {
        $setting = TenantSetting::query()
            ->forTenant($context->tenantId())
            ->firstOrFail();

        return new TenantSettingResource($setting);
    }

    /**
     * Update the active tenant's operational settings.
     */
    public function update(
        UpdateTenantSettingsRequest $request,
        TenantContext $context,
        TenantSettingsService $settings,
    ): TenantSettingResource {
        /** @var Tenant $tenant */
        $tenant = $context->tenant();

        Gate::authorize('manageSettings', $tenant);

        $setting = $settings->update($tenant, $request->validated(), $request->user());

        return new TenantSettingResource($setting);
    }
}
