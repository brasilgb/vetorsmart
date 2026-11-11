<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\RequestOrigin;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Workflow of a service request: Solicitação → Triagem → Agendamento → Execução → Conclusão.
 * Every change runs in a transaction under a row lock on the request.
 */
class ServiceRequestService
{
    public function __construct(private AuditLogger $auditLogger) {}

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
            ], $actor);

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
    public function complete(ServiceRequest $request, User $actor): ServiceRequest
    {
        return $this->move($request, RequestStatus::Completed, $actor, 'Atendimento concluído.', AppointmentStatus::Completed);
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
                    ->each(fn (Appointment $appointment) => $appointment->forceFill([
                        'status' => $appointmentStatus,
                        'cancelling_reason' => $appointmentStatus === AppointmentStatus::Cancelled ? $closingReason : null,
                        'cancelled_at' => $appointmentStatus === AppointmentStatus::Cancelled ? now() : null,
                        'cancelled_by_user_id' => $appointmentStatus === AppointmentStatus::Cancelled ? $actor->getKey() : null,
                    ])->save());
            }

            $this->auditLogger->record('service_request.'.$target->value, $request->tenant, $request, [
                'to' => $target->value,
            ], $actor);

            return $request;
        });
    }
}
