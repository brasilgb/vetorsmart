<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\TenantSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $business_name
 * @property string|null $whatsapp
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string $locale
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['tenant_id', 'business_name', 'whatsapp', 'address', 'city', 'state', 'locale', 'settings'])]
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
            'settings' => 'array',
        ];
    }
}
