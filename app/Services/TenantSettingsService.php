<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;

class TenantSettingsService
{
    /**
     * Fields stored on the tenant record rather than on its settings.
     *
     * @var list<string>
     */
    private const array TENANT_FIELDS = ['phone', 'timezone', 'segment_id'];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Update the tenant's operational settings. Phone and timezone are written to the tenant record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Tenant $tenant, array $attributes, User $actor): TenantSetting
    {
        $tenantChanges = array_intersect_key($attributes, array_flip(self::TENANT_FIELDS));
        $settingChanges = array_diff_key($attributes, array_flip(self::TENANT_FIELDS));

        $setting = TenantSetting::query()->forTenant($tenant)->firstOrFail();
        $setting->fill($settingChanges);
        $changedSettings = array_keys($setting->getDirty());
        $setting->save();

        $tenant->fill($tenantChanges);
        $changedTenant = array_keys($tenant->getDirty());
        $tenant->save();

        $changedFields = [...$changedSettings, ...$changedTenant];

        if ($changedFields !== []) {
            $this->auditLogger->record('tenant_settings.updated', $tenant, $setting, [
                'fields' => $changedFields,
            ], $actor);
        }

        return $setting->setRelation('tenant', $tenant);
    }
}
