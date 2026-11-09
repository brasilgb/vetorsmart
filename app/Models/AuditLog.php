<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'metadata'])]
class AuditLog extends Model
{
    /**
     * Audit entries are append-only and have no update timestamp.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
