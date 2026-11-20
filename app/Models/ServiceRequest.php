<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\AppointmentStatus;
use App\Enums\AssistantMessageRole;
use App\Enums\ConversationStatus;
use App\Enums\DiscoverySource;
use App\Enums\RequestOrigin;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property int|null $service_id
 * @property int|null $professional_id
 * @property string $description
 * @property RequestOrigin $origin
 * @property DiscoverySource|null $discovery how the customer found the company (null = unknown)
 * @property string|null $utm_source campaign tags of the visit (complementary, declared by the link; never a portal proof)
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property RequestPriority $priority
 * @property RequestStatus $status
 * @property string|null $closing_reason
 * @property Carbon|null $closed_at
 * @property int|null $created_by_user_id
 * @property int|null $assigned_user_id
 * @property Carbon|null $assigned_at
 * @property int|null $realized_amount_cents amount declared by the company for a completed service; null means unknown
 * @property Carbon|null $realized_amount_recorded_at
 * @property int|null $realized_amount_recorded_by
 */
#[Fillable(['tenant_id', 'customer_id', 'service_id', 'professional_id', 'description', 'origin', 'discovery', 'utm_source', 'utm_medium', 'utm_campaign', 'priority', 'status', 'closing_reason', 'created_by_user_id', 'closed_at', 'assigned_user_id', 'assigned_at', 'realized_amount_cents', 'realized_amount_recorded_at', 'realized_amount_recorded_by'])]
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
     * The member of the company responsible for the request (not the professional of the appointment).
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
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
     * Commercial origin of the request (how the customer found the company), from the first reliable record kept at
     * creation, in this order: the Regional Portal verified by its signed link; the campaign tags of the visit
     * (declared by the link, complementary); otherwise not identified. Never inferred from the channel or from who
     * handled the request.
     *
     * @return array{key: string, label: string, detail: string|null}
     */
    public function commercialOrigin(): array
    {
        if ($this->discovery === DiscoverySource::Portal) {
            return ['key' => 'portal', 'label' => 'Portal Regional', 'detail' => 'chegada confirmada pelo link do portal'];
        }

        if ($this->utm_source !== null) {
            return ['key' => 'campaign', 'label' => 'Campanha', 'detail' => implode(' · ', array_filter([$this->utm_source, $this->utm_medium, $this->utm_campaign]))];
        }

        return ['key' => 'unknown', 'label' => 'Não identificada', 'detail' => null];
    }

    /**
     * Assistant conversations that registered this request (normally one). Not scoped by tenant on its own: load it
     * through attendanceRelations(), which limits it to the request's company.
     *
     * @return HasMany<AssistantConversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class)->orderBy('id');
    }

    /**
     * Eager loads needed by attendance(), limited to one company: the conversation, the attendant who assumed it and
     * whether the team wrote in it.
     *
     * @return array<string, \Closure>
     */
    public static function attendanceRelations(int $tenantId): array
    {
        return [
            'conversations' => fn (HasMany $query) => $query
                ->where('tenant_id', $tenantId)
                ->with('handledBy:id,name')
                ->withExists(['messages as team_replied' => fn (Builder $messages) => $messages->where('role', AssistantMessageRole::Operator->value)]),
        ];
    }

    /**
     * Who conducted the request, from records already kept and apart from the channel and the commercial origin: the
     * virtual assistant alone, the assistant with a hand-off to the team (a hand-off reason, a conversation waiting for
     * or held by a person, or a reply written by the team), or the team when no conversation registered it. A request
     * from the assistant whose conversation is no longer available (retention) stays with the virtual assistant.
     * The person responsible (INTEGRATION-02) is shown apart. Expects attendanceRelations() loaded.
     *
     * @return array{key: string, label: string, detail: string|null}
     */
    public function attendance(): array
    {
        $conversation = $this->conversations->firstWhere('tenant_id', $this->tenant_id);

        if ($conversation === null) {
            return $this->origin === RequestOrigin::Ai
                ? ['key' => 'assistant', 'label' => 'Atendimento virtual', 'detail' => 'conversa não disponível']
                : ['key' => 'team', 'label' => 'Equipe', 'detail' => null];
        }

        $handedOff = $conversation->handoff_reason !== null
            || in_array($conversation->status, [ConversationStatus::AwaitingHuman, ConversationStatus::HumanAssumed], true)
            || (bool) $conversation->getAttribute('team_replied');

        if (! $handedOff) {
            return ['key' => 'assistant', 'label' => 'Atendimento virtual', 'detail' => null];
        }

        $detail = match (true) {
            $conversation->status === ConversationStatus::AwaitingHuman => 'aguardando a equipe',
            $conversation->handledBy !== null => 'atendido por '.$conversation->handledBy->name,
            default => null,
        };

        return ['key' => 'assistant_team', 'label' => 'Atendimento virtual, encaminhado à equipe', 'detail' => $detail];
    }

    /**
     * The three views of where a request came from and who conducted it, ready for the panel screens.
     *
     * @return array{commercial_origin: array{key: string, label: string, detail: string|null}, channel: array{key: string, label: string}, attendance: array{key: string, label: string, detail: string|null}}
     */
    public function traceability(): array
    {
        return [
            'commercial_origin' => $this->commercialOrigin(),
            'channel' => $this->origin->channel(),
            'attendance' => $this->attendance(),
        ];
    }

    /**
     * Days without any history entry after which an open request counts as stalled.
     */
    public const int STALE_AFTER_DAYS = 7;

    /**
     * Open requests without movement: no history entry in the last STALE_AFTER_DAYS days, and, when scheduled, no
     * appointment still ahead (a visit that already passed without being started also counts).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeStalled(Builder $query): Builder
    {
        $limit = now()->subDays(self::STALE_AFTER_DAYS);

        return $query
            ->whereIn($this->qualifyColumn('status'), [RequestStatus::Requested->value, RequestStatus::Triaged->value, RequestStatus::Scheduled->value, RequestStatus::InProgress->value])
            ->where($this->qualifyColumn('created_at'), '<', $limit)
            ->whereDoesntHave('events', fn (Builder $events) => $events->where('created_at', '>=', $limit))
            ->where(fn (Builder $scheduled) => $scheduled
                ->where($this->qualifyColumn('status'), '!=', RequestStatus::Scheduled->value)
                ->orWhereDoesntHave('appointments', fn (Builder $appointments) => $appointments
                    ->where('status', AppointmentStatus::Scheduled->value)
                    ->where('starts_at', '>=', now()->subDay())));
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
            'discovery' => DiscoverySource::class,
            'priority' => RequestPriority::class,
            'status' => RequestStatus::class,
            'closed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'realized_amount_cents' => 'integer',
            'realized_amount_recorded_at' => 'datetime',
        ];
    }
}
