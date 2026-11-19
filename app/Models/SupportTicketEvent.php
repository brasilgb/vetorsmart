<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only follow-up of a support record: opened, comment, status or priority change.
 *
 * @property int $id
 * @property int $support_ticket_id
 * @property int|null $user_id
 * @property string $type
 * @property string|null $from_value
 * @property string|null $to_value
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property User|null $user
 */
class SupportTicketEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
