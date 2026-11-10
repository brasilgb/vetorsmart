<?php

namespace App\Models;

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property Segment|null $segment
 * @property string $slug
 * @property string|null $document
 * @property string|null $email
 * @property string|null $phone
 * @property int|null $segment_id
 * @property TenantStatus $status
 * @property string $timezone
 */
#[Fillable(['uuid', 'name', 'slug', 'document', 'email', 'phone', 'segment_id', 'status', 'timezone'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * Determine whether the tenant can be used.
     */
    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    /**
     * @return BelongsTo<Segment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /**
     * @return HasMany<TenantUser, $this>
     */
    public function owners(): HasMany
    {
        return $this->hasMany(TenantUser::class)->where('role', TenantRole::Owner);
    }

    /**
     * @return HasMany<TenantUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * @return BelongsToMany<User, $this, TenantUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TenantUser::class)
            ->withPivot(['id', 'role', 'status'])
            ->withTimestamps();
    }

    /**
     * @return HasOne<TenantSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }
}
