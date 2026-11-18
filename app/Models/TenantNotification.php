<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A notice shown in the panel to one person of one company.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property NotificationCategory $category
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
#[Fillable(['tenant_id', 'user_id', 'category', 'title', 'body', 'url'])]
class TenantNotification extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    /**
     * Notices of one person within one company.
     *
     * @param  Builder<TenantNotification>  $query
     * @return Builder<TenantNotification>
     */
    public function scopeOwnedBy(Builder $query, Tenant|int $tenant, User|int $user): Builder
    {
        return $query->forTenant($tenant)->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'read_at' => 'datetime',
        ];
    }
}
