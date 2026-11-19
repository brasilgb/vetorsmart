<?php

namespace App\Models;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Operational support record of the platform team. Changed only through the RootAdmin support controller, which
 * writes the follow-up history and the audit log.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $subject
 * @property string $description
 * @property SupportTicketStatus $status
 * @property SupportTicketPriority $priority
 * @property int|null $opened_by
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Tenant|null $tenant
 * @property User|null $opener
 */
class SupportTicket extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return HasMany<SupportTicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SupportTicketEvent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
            'priority' => SupportTicketPriority::class,
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
