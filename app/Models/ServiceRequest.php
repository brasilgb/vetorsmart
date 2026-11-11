<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\RequestOrigin;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property int|null $service_id
 * @property int|null $professional_id
 * @property string $description
 * @property RequestOrigin $origin
 * @property RequestPriority $priority
 * @property RequestStatus $status
 * @property string|null $closing_reason
 * @property int|null $created_by_user_id
 */
#[Fillable(['tenant_id', 'customer_id', 'service_id', 'professional_id', 'description', 'origin', 'priority', 'status', 'closing_reason', 'created_by_user_id', 'closed_at'])]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use BelongsToTenant, HasFactory;

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
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /**
     * @return HasMany<ServiceRequestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ServiceRequestEvent::class)->orderBy('id');
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('starts_at');
    }

    /**
     * Move the request to a new status along the workflow and append the history entry.
     * Refused with a validation error when the transition is not allowed.
     *
     * @throws ValidationException
     */
    public function transitionTo(RequestStatus $target, ?User $actor, ?string $note = null, ?string $closingReason = null): void
    {
        $from = $this->status;

        if (! $from->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => ['Esta mudança de situação não é permitida para a solicitação atual.'],
            ]);
        }

        $this->forceFill([
            'status' => $target,
            'closing_reason' => $target->isOpen() ? null : ($closingReason ?? $this->closing_reason),
            'closed_at' => $target->isOpen() ? null : now(),
        ])->save();

        ServiceRequestEvent::create([
            'tenant_id' => $this->tenant_id,
            'service_request_id' => $this->getKey(),
            'type' => 'status_changed',
            'from_status' => $from->value,
            'to_status' => $target->value,
            'note' => $note,
            'user_id' => $actor?->getKey(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => RequestOrigin::class,
            'priority' => RequestPriority::class,
            'status' => RequestStatus::class,
            'closed_at' => 'datetime',
        ];
    }
}
