<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTenantSettingsRequest;
use App\Http\Resources\Api\V1\TenantSettingResource;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantContext;
use App\Services\TenantSettingsService;

class TenantSettingsController extends Controller
{
    /**
     * Show the active tenant's operational settings.
     */
    public function show(TenantContext $context): TenantSettingResource
    {
        $tenant = $context->requireTenant();

        $setting = TenantSetting::query()->forTenant($tenant)->firstOrFail();

        return new TenantSettingResource($setting->setRelation('tenant', $tenant));
    }

    /**
     * Update the active tenant's operational settings.
     */
    public function update(
        UpdateTenantSettingsRequest $request,
        TenantContext $context,
        TenantSettingsService $settings,
    ): TenantSettingResource {
        $tenant = $context->requireTenant();

        $this->authorize('manageSettings', $tenant);

        return new TenantSettingResource($settings->update($tenant, $request->validated(), $this->actor($request)));
    }
}
