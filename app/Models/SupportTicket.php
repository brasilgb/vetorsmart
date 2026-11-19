<?php

namespace App\Models;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A support request. Opened by a company (Central de Suporte) or by the platform team; changed only through
 * SupportTicketService, which writes the append-only history and the audit log. A company sees only its own tickets
 * and never the internal notes.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $subject
 * @property string $description
 * @property SupportTicketCategory $category
 * @property string $opened_via
 * @property int|null $assigned_to
 * @property Carbon|null $due_at
 * @property SupportTicketStatus $status
 * @property SupportTicketPriority $priority
 * @property int|null $opened_by
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Tenant|null $tenant
 * @property User|null $opener
 * @property User|null $assignee
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Past its due date and still not resolved or closed.
     */
    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast() && ! $this->status->isFinal();
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
            'category' => SupportTicketCategory::class,
            'due_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
