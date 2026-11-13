<?php

namespace App\Services\Assistant;

use App\Enums\ConversationStatus;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\AssistantConversation;
use App\Models\AssistantOperation;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AppointmentScheduler;
use App\Services\AuditLogger;
use App\Services\CustomerService;
use App\Services\ServiceRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Internal operations used by the visitor assistant. Each one receives the tenant explicitly and
 * reuses the existing services, so the assistant never writes to the database directly and never
 * chooses a tenant from the conversation text. Scheduling rules live in AppointmentScheduler only.
 */
class AssistantOperations
{
    public function __construct(
        private AppointmentScheduler $scheduler,
        private ServiceRequestService $requests,
        private CustomerService $customers,
        private AssistantConversations $conversations,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @return Collection<int, Service>
     */
    public function activeServices(Tenant $tenant): Collection
    {
        return Service::query()
            ->forTenant($tenant)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Description, duration and price exactly as stored. The assistant never invents these values.
     *
     * @return array{id: int, name: string, description: ?string, duration_minutes: ?int, price: ?string, price_type: string}
     */
    public function serviceDetails(Service $service): array
    {
        return [
            'id' => $service->getKey(),
            'name' => $service->name,
            'description' => $service->description,
            'duration_minutes' => $service->duration_minutes,
            'price' => $service->price === null ? null : number_format((float) $service->price, 2, ',', '.'),
            'price_type' => $service->price_type->value,
        ];
    }

    /**
     * Active professionals linked to the service.
     *
     * @return Collection<int, Professional>
     */
    public function enabledProfessionals(Tenant $tenant, Service $service): Collection
    {
        return $service->professionals()
            ->where('professionals.tenant_id', $tenant->getKey())
            ->where('professionals.active', true)
            ->orderBy('professionals.name')
            ->get();
    }

    /**
     * Free start times on a local day, across the enabled professionals, sorted by time.
     * Each option carries the UTC instant the booking will use.
     *
     * @return list<array{professional_id: int, professional: string, time: string, starts_at: string}>
     */
    public function availableSlots(Tenant $tenant, Service $service, CarbonImmutable $day): array
    {
        $options = [];

        foreach ($this->enabledProfessionals($tenant, $service) as $professional) {
            foreach ($this->scheduler->availableSlots($professional, $service, $day) as $time) {
                $options[] = [
                    'professional_id' => $professional->getKey(),
                    'professional' => $professional->name,
                    'time' => $time,
                    'starts_at' => $day->setTimeFromTimeString($time.':00')->utc()->toIso8601String(),
                ];
            }
        }

        usort($options, fn (array $a, array $b): int => [$a['time'], $a['professional']] <=> [$b['time'], $b['professional']]);

        return $options;
    }

    /**
     * Find the customer by phone or e-mail before creating one, so a visitor who already exists is
     * not registered twice.
     */
    public function findOrCreateCustomer(Tenant $tenant, string $name, string $phone): Customer
    {
        $existing = Customer::query()
            ->forTenant($tenant)
            ->where(fn ($query) => $query->where('phone', $phone)->orWhere('whatsapp', $phone))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->customers->create($tenant, ['name' => $name, 'phone' => $phone], null);
    }

    /**
     * Register the visitor's request in the company's triage queue, with origin "Site / IA" and the conversation summary.
     * Called when the attendance finishes. The conversation holds one open request: a repeated call updates its
     * summary and returns the same request, never a second one. The request is triaged to the service when known.
     */
    public function registerRequest(Tenant $tenant, AssistantConversation $conversation, string $summary): ServiceRequest
    {
        return DB::transaction(function () use ($tenant, $conversation, $summary): ServiceRequest {
            $locked = AssistantConversation::query()->whereKey($conversation->getKey())->lockForUpdate()->firstOrFail();

            $request = $locked->service_request_id === null
                ? null
                : ServiceRequest::query()->forTenant($tenant)->whereKey($locked->service_request_id)->lockForUpdate()->first();

            if ($request === null || ! $request->status->isOpen()) {
                $request = $this->requests->create($tenant, [
                    'customer_id' => $locked->customer_id,
                    'description' => $summary,
                ], null, RequestOrigin::Ai);
            } else {
                $request->forceFill(['description' => $summary])->save();
            }

            if ($locked->service_id !== null && $request->status === RequestStatus::Requested) {
                try {
                    $request = $this->requests->triage($request, ['service_id' => $locked->service_id], null);
                } catch (ValidationException) {
                    // The service was deactivated after the choice: the request stays in the queue, not triaged.
                }
            }

            $locked->forceFill(['service_request_id' => $request->getKey()])->save();
            $conversation->forceFill(['service_request_id' => $request->getKey()]);

            return $request;
        });
    }

    /**
     * The team identifies the visitor of a conversation that is waiting for a human and turns it into a request in the triage queue.
     * The messages stay in the conversation. Repeating it updates the same request (see registerRequest).
     *
     * @throws ValidationException when the conversation is already closed
     */
    public function identifyConversation(Tenant $tenant, AssistantConversation $conversation, string $name, string $phone, ?Service $service, User $actor): ServiceRequest
    {
        return DB::transaction(function () use ($tenant, $conversation, $name, $phone, $service, $actor): ServiceRequest {
            $locked = AssistantConversation::query()->whereKey($conversation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === ConversationStatus::Closed) {
                throw ValidationException::withMessages(['status' => ['Esta conversa já foi encerrada.']]);
            }

            $customer = $this->findOrCreateCustomer($tenant, $name, $phone);

            $locked->forceFill([
                'customer_id' => $customer->getKey(),
                'service_id' => $service?->getKey() ?? $locked->service_id,
            ])->save();

            $request = $this->registerRequest(
                $tenant,
                $locked,
                $this->conversations->summarize($locked, 'Cliente identificado pela equipe; solicitação encaminhada à triagem.'),
            );

            $this->auditLogger->record('assistant.conversation_identified', $tenant, $locked, ['service_request_id' => $request->getKey()], $actor);

            return $request;
        });
    }

    /**
     * Book the chosen slot through the scheduler, which confirms availability again inside its own
     * transaction. The idempotency key guarantees that a repeated confirmation returns the same
     * appointment instead of creating a second one.
     *
     * @throws ValidationException when the slot is no longer available
     */
    public function requestAppointment(Tenant $tenant, AssistantConversation $conversation, ServiceRequest $request, Professional $professional, CarbonImmutable $startsAt, string $idempotencyKey): Appointment
    {
        return DB::transaction(function () use ($tenant, $conversation, $request, $professional, $startsAt, $idempotencyKey): Appointment {
            AssistantConversation::query()->whereKey($conversation->getKey())->lockForUpdate()->firstOrFail();

            $previous = AssistantOperation::query()
                ->forTenant($tenant)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($previous !== null) {
                return Appointment::query()->forTenant($tenant)->whereKey($previous->result_id)->firstOrFail();
            }

            $appointment = $this->scheduler->schedule($request, $professional, $startsAt, null);

            AssistantOperation::create([
                'tenant_id' => $tenant->getKey(),
                'assistant_conversation_id' => $conversation->getKey(),
                'operation' => 'request_appointment',
                'idempotency_key' => $idempotencyKey,
                'result_type' => 'appointment',
                'result_id' => $appointment->getKey(),
            ]);

            $conversation->forceFill(['appointment_id' => $appointment->getKey()])->save();

            return $appointment;
        });
    }

    /**
     * Status of the visitor's request and appointment, read from the database.
     *
     * @return array{request_status: ?string, appointment: ?string}
     */
    public function requestStatus(Tenant $tenant, AssistantConversation $conversation): array
    {
        $request = $conversation->service_request_id === null
            ? null
            : ServiceRequest::query()->forTenant($tenant)->whereKey($conversation->service_request_id)->first();

        $appointment = $conversation->appointment_id === null
            ? null
            : Appointment::query()->forTenant($tenant)->whereKey($conversation->appointment_id)->first();

        return [
            'request_status' => $request?->status->label(),
            'appointment' => $appointment === null ? null : $tenant->toLocal($appointment->starts_at)->format('d/m/Y').' às '.$tenant->toLocal($appointment->starts_at)->format('H:i'),
        ];
    }

    /**
     * The idempotency key for a booking attempt: the same conversation, service, professional and instant always give the same key.
     */
    public function bookingKey(AssistantConversation $conversation, Professional $professional, CarbonImmutable $startsAt): string
    {
        return hash('sha256', implode('|', [
            $conversation->getKey(),
            $professional->getKey(),
            $startsAt->utc()->toIso8601String(),
        ]));
    }
}
