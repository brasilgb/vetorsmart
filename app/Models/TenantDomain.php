<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\DomainKind;
use App\Enums\DomainStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property Carbon|null $verified_at
 * @property string $hostname
 * @property DomainKind $kind
 * @property DomainStatus $status
 * @property string|null $verification_token
 * @property bool $is_primary
 */
#[Fillable(['tenant_id', 'hostname', 'kind', 'status', 'verification_token', 'is_primary', 'verified_at'])]
class TenantDomain extends Model
{
    use BelongsToTenant;

    public function isServing(): bool
    {
        return $this->status === DomainStatus::Verified;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DomainKind::class,
            'status' => DomainStatus::class,
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
