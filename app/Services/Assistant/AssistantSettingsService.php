<?php

namespace App\Services\Assistant;

use App\Models\AssistantSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Per-tenant configuration of the visitor assistant. Only the fields the company may edit are
 * accepted; daily limits stay at the platform defaults so consumption is not set by the tenant.
 */
class AssistantSettingsService
{
    /** @var list<string> */
    private const array EDITABLE = [
        'enabled', 'display_name', 'greeting', 'presentation', 'service_region',
        'business_hours_text', 'commercial_info', 'guidance', 'handoff_conditions', 'retention_days',
    ];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Tenant $tenant, array $attributes, User $actor): AssistantSetting
    {
        return DB::transaction(function () use ($tenant, $attributes, $actor): AssistantSetting {
            $setting = AssistantSetting::query()->forTenant($tenant)->lockForUpdate()->first()
                ?? new AssistantSetting(['tenant_id' => $tenant->getKey()]);

            $data = array_intersect_key($attributes, array_flip(self::EDITABLE));
            $setting->fill($data);
            $changed = array_keys($setting->getDirty());
            $setting->save();

            if ($changed !== []) {
                $this->auditLogger->record('assistant.settings_updated', $tenant, $setting, ['fields' => $changed], $actor);
            }

            return $setting;
        });
    }
}
