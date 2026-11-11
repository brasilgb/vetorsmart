<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant configuration of the visitor assistant. Read by the engine, never shared between tenants.
 */
#[Fillable(['tenant_id', 'enabled', 'display_name', 'greeting', 'presentation', 'service_region', 'business_hours_text', 'commercial_info', 'guidance', 'handoff_conditions', 'daily_message_limit', 'daily_token_limit', 'retention_days'])]
class AssistantSetting extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
