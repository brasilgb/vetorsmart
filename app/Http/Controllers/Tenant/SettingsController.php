<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTenantSettingsRequest;
use App\Http\Resources\Api\V1\TenantSettingResource;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantContext;
use App\Services\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company settings in sections. Each section posts only its own fields; members
 * can read every section, but only owners and admins can change them.
 */
class SettingsController extends Controller
{
    public function show(TenantContext $context): Response
    {
        $tenant = $context->requireTenant();

        $setting = TenantSetting::query()->forTenant($tenant)->firstOrFail()->setRelation('tenant', $tenant);

        return Inertia::render('tenant/settings/index', [
            'settings' => (new TenantSettingResource($setting))->resolve(),
            'segments' => Segment::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn (Segment $segment): array => ['id' => $segment->id, 'name' => $segment->name])->values()->all(),
            'canEdit' => Gate::allows('manageSettings', $tenant),
        ]);
    }

    public function update(UpdateTenantSettingsRequest $request, TenantContext $context, TenantSettingsService $settings): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $context->requireTenant();

        Gate::authorize('manageSettings', $tenant);

        $settings->update($tenant, $request->validated(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configurações salvas.']);

        return to_route('tenant.settings.show');
    }
}
