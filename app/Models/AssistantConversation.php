<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\AssistantState;
use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A visitor conversation. The session is identified by a hash of a random token; the token itself is never stored.
 */
#[Fillable(['tenant_id', 'channel', 'session_hash', 'customer_id', 'state', 'status', 'service_id', 'service_request_id', 'appointment_id', 'handled_by_user_id', 'collected', 'handoff_reason', 'invalid_attempts', 'last_activity_at', 'retention_until'])]
class AssistantConversation extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => AssistantState::class,
            'status' => ConversationStatus::class,
            'collected' => 'array',
            'last_activity_at' => 'datetime',
            'retention_until' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AssistantMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
