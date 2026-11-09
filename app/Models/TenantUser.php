<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\MembershipStatus;
use App\Enums\TenantRole;
use Database\Factories\TenantUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property TenantRole $role
 * @property MembershipStatus $status
 */
#[Fillable(['tenant_id', 'user_id', 'role', 'status'])]
class TenantUser extends Pivot
{
    /** @use HasFactory<TenantUserFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_users';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether the membership grants access.
     */
    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'status' => MembershipStatus::class,
        ];
    }
}
