<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ConfirmationPolicy;
use Database\Factories\TenantSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $business_name
 * @property string|null $description
 * @property string|null $whatsapp
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string $locale
 * @property list<string>|null $service_modes
 * @property string|null $welcome_message
 * @property ConfirmationPolicy $confirmation_policy
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['tenant_id', 'business_name', 'description', 'whatsapp', 'address', 'city', 'state', 'locale', 'service_modes', 'welcome_message', 'confirmation_policy', 'settings'])]
class TenantSetting extends Model
{
    /** @use HasFactory<TenantSettingFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_modes' => 'array',
            'confirmation_policy' => ConfirmationPolicy::class,
            'settings' => 'array',
        ];
    }
}
