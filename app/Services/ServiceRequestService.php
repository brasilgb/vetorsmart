<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\DiscoverySource;
use App\Enums\MembershipStatus;
use App\Enums\RequestOrigin;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestEvent;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Workflow of a service request: Solicitação → Triagem → Agendamento → Execução → Conclusão.
 * Every change runs in a transaction under a row lock on the request.
 */
class ServiceRequestService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private OperationalNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Tenant $tenant, array $attributes, ?User $actor, RequestOrigin $origin = RequestOrigin::Panel): ServiceRequest
    {
        return DB::transaction(function () use ($tenant, $attributes, $actor, $origin): ServiceRequest {
            $request = ServiceRequest::create([
                'tenant_id' => $tenant->getKey(),
                'customer_id' => $attributes['customer_id'],
                'description' => $attributes['description'],
                'origin' => $origin,
                // Discovery comes only from the server (a verified portal arrival); the portal form implies it.
                'discovery' => $origin === RequestOrigin::Portal ? DiscoverySource::Portal : (($attributes['discovery'] ?? null) instanceof DiscoverySource ? $attributes['discovery'] : null),
                'priority' => $attributes['priority'] ?? RequestPriority::Normal,
                'status' => RequestStatus::Requested,
                'created_by_user_id' => $actor?->getKey(),
            ]);

            ServiceRequestEvent::create([
                'tenant_id' => $tenant->getKey(),
                'service_request_id' => $request->getKey(),
                'type' => 'created',
                'to_status' => RequestStatus::Requested->value,
                'user_id' => $actor?->getKey(),
            ]);

            $this->auditLogger->record('service_request.created', $tenant, $request, [
                'origin' => $origin->value,
                'discovery' => $request->discovery?->value,
            ], $actor);

            $this->notifier->requestReceived($request, $actor);

            return $request;
        });
    }

    /**
     * Set the service and priority. The first triage moves the request from requested to triaged;
     * later changes keep the status and are recorded as a history entry.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function triage(ServiceRequest $request, array $attributes, ?User $actor): ServiceRequest
    {
        return DB::transaction(function () use ($request, $attributes, $actor): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($request->status, [RequestStatus::Requested, RequestStatus::Triaged], true)) {
                throw ValidationException::withMessages(['status' => ['Somente solicitações abertas podem ser triadas.']]);
            }

            $service = Service::query()->forTenant($request->tenant_id)->whereKey($attributes['service_id'])->firstOrFail();

            if (! $service->isAvailableForScheduling()) {
                throw ValidationException::withMessages(['service_id' => ['Este serviço está inativo.']]);
            }

            $request->forceFill([
                'service_id' => $service->getKey(),
                'priority' => $attributes['priority'] ?? $request->priority,
            ])->save();

            if ($request->status === RequestStatus::Requested) {
                $request->transitionTo(RequestStatus::Triaged, $actor, 'Serviço: '.$service->name.'.');
            } else {
                ServiceRequestEvent::create([
                    'tenant_id' => $request->tenant_id,
                    'service_request_id' => $request->getKey(),
                    'type' => 'triage_updated',
                    'note' => 'Serviço: '.$service->name.'.',
                    'user_id' => $actor?->getKey(),
                ]);
            }

            return $request;
        });
    }

    /**
     * Start the service of a scheduled request.
     */
    public function start(ServiceRequest $request, User $actor): ServiceRequest
    {
        return $this->move($request, RequestStatus::InProgress, $actor, 'Atendimento iniciado.');
    }

    /**
     * Conclude a request in progress; its appointment is marked as completed too.
     */
    public function complete(ServiceRequest $request, User $actor, ?int $realizedAmountCents = null): ServiceRequest
    {
        return DB::transaction(function () use ($request, $actor, $realizedAmountCents): ServiceRequest {
            $request = $this->move($request, RequestStatus::Completed, $actor, 'Atendimento concluído.', AppointmentStatus::Completed);

            return $realizedAmountCents === null ? $request : $this->recordRealizedAmount($request, $realizedAmountCents, $actor);
        });
    }

    /**
     * Record or correct the amount the company declares it received for a completed service. It is an operational
     * figure informed by the company, not a proof of payment. Only completed requests take an amount, so cancelled or
     * unperformed ones never produce revenue; one amount per request, so a service is never counted twice. Null means
     * unknown (never zero). Every change goes to the history and to the audit trail with the previous value.
     *
     * @throws ValidationException
     */
    public function recordRealizedAmount(ServiceRequest $request, ?int $cents, User $actor): ServiceRequest
    {
        return DB::transaction(function () use ($request, $cents, $actor): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status !== RequestStatus::Completed) {
                throw ValidationException::withMessages(['realized_amount' => ['Informe o valor realizado somente em atendimentos concluídos.']]);
            }

            if ($cents !== null && $cents < 0) {
                throw ValidationException::withMessages(['realized_amount' => ['Informe um valor igual ou maior que zero.']]);
            }

            $previous = $request->realized_amount_cents;

            if ($previous === $cents) {
                return $request;
            }

            $request->forceFill([
                'realized_amount_cents' => $cents,
                'realized_amount_recorded_at' => now(),
                'realized_amount_recorded_by' => $actor->getKey(),
            ])->save();

            $describe = fn (?int $value): string => $value === null ? 'não informado' : Money::format($value);

            ServiceRequestEvent::create([
                'tenant_id' => $request->tenant_id,
                'service_request_id' => $request->getKey(),
                'type' => 'realized_amount',
                'note' => 'Valor realizado: '.$describe($cents).($previous === null ? '' : ' (antes: '.$describe($previous).')'),
                'user_id' => $actor->getKey(),
            ]);

            $this->auditLogger->record('service_request.realized_amount', $request->tenant, $request, ['from_cents' => $previous, 'to_cents' => $cents], $actor);

            return $request;
        });
    }

    /**
     * Make a member of the company responsible for the request, or take the responsibility away (null). The member must
     * belong to the same company and be active, both as a person and as a member. Only open requests are assigned.
     * The change goes to the history (previous and new responsible, author, date) and the new responsible is told.
     *
     * @throws ValidationException
     */
    public function assign(ServiceRequest $request, ?User $assignee, User $actor): ServiceRequest
    {
        return DB::transaction(function () use ($request, $assignee, $actor): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $request->status->isOpen()) {
                throw ValidationException::withMessages(['assigned_user_id' => ['Somente solicitações em aberto podem mudar de responsável.']]);
            }

            if ($assignee !== null && ! $this->isActiveMember($request->tenant_id, $assignee)) {
                throw ValidationException::withMessages(['assigned_user_id' => ['Escolha uma pessoa ativa da equipe desta empresa.']]);
            }

            $previous = $request->assigned_user_id === null ? null : User::query()->find($request->assigned_user_id);

            if ($previous?->getKey() === $assignee?->getKey()) {
                return $request;
            }

            $request->forceFill(['assigned_user_id' => $assignee?->getKey(), 'assigned_at' => $assignee === null ? null : now()])->save();

            $event = ServiceRequestEvent::create([
                'tenant_id' => $request->tenant_id,
                'service_request_id' => $request->getKey(),
                'type' => 'assigned',
                'note' => 'Responsável: '.($previous->name ?? 'ninguém').' → '.($assignee->name ?? 'ninguém'),
                'user_id' => $actor->getKey(),
            ]);

            $this->auditLogger->record('service_request.assigned', $request->tenant, $request, ['from' => $previous?->getKey(), 'to' => $assignee?->getKey()], $actor);

            if ($assignee !== null) {
                $this->notifier->requestAssigned($request, $assignee, (int) $event->getKey(), $actor);
            }

            return $request;
        });
    }

    private function isActiveMember(int $tenantId, User $user): bool
    {
        return $user->status === UserStatus::Active && TenantUser::query()
            ->forTenant($tenantId)
            ->where('user_id', $user->getKey())
            ->where('status', MembershipStatus::Active->value)
            ->exists();
    }

    /**
     * Register that the service did not happen. A reason is required.
     */
    public function markNotPerformed(ServiceRequest $request, string $reason, User $actor): ServiceRequest
    {
        return $this->move($request, RequestStatus::NotPerformed, $actor, $reason, AppointmentStatus::NotPerformed, $reason);
    }

    /**
     * Cancel an open request. A reason is required, and any active appointment is cancelled too.
     */
    public function cancel(ServiceRequest $request, string $reason, User $actor): ServiceRequest
    {
        return $this->move($request, RequestStatus::Cancelled, $actor, $reason, AppointmentStatus::Cancelled, $reason);
    }

    private function move(
        ServiceRequest $request,
        RequestStatus $target,
        User $actor,
        string $note,
        ?AppointmentStatus $appointmentStatus = null,
        ?string $closingReason = null,
    ): ServiceRequest {
        return DB::transaction(function () use ($request, $target, $actor, $note, $appointmentStatus, $closingReason): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            $request->transitionTo($target, $actor, $note, $closingReason);

            if ($appointmentStatus !== null) {
                Appointment::query()
                    ->where('service_request_id', $request->getKey())
                    ->where('status', AppointmentStatus::Scheduled->value)
                    ->get()
                    ->each(function (Appointment $appointment) use ($appointmentStatus, $closingReason, $actor): void {
                        $appointment->forceFill([
                            'status' => $appointmentStatus,
                            'cancelling_reason' => $appointmentStatus === AppointmentStatus::Cancelled ? $closingReason : null,
                            'cancelled_at' => $appointmentStatus === AppointmentStatus::Cancelled ? now() : null,
                            'cancelled_by_user_id' => $appointmentStatus === AppointmentStatus::Cancelled ? $actor->getKey() : null,
                        ])->save();

                        if ($appointmentStatus === AppointmentStatus::Cancelled) {
                            $this->notifier->appointmentCancelled($appointment, $actor);
                        }
                    });
            }

            $this->auditLogger->record('service_request.'.$target->value, $request->tenant, $request, [
                'to' => $target->value,
            ], $actor);

            if ($target === RequestStatus::Completed) {
                $this->notifier->serviceCompleted($request);
            }

            return $request;
        });
    }
}
