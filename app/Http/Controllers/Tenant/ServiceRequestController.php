<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Operations\ReasonRequest;
use App\Http\Requests\Tenant\Operations\ScheduleAppointmentRequest;
use App\Http\Requests\Tenant\Operations\StoreServiceRequestRequest;
use App\Http\Requests\Tenant\Operations\TriageServiceRequestRequest;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\AppointmentScheduler;
use App\Services\ServiceRequestService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', ServiceRequest::class);

        $query = ServiceRequest::query()
            ->forTenant($context->requireTenant())
            ->with(['customer:id,name', 'service:id,name'])
            ->latest('id');

        $status = $request->string('status')->value();

        if (RequestStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        return Inertia::render('tenant/requests/index', [
            'filters' => ['status' => $status],
            'statuses' => $this->statusOptions(),
            'requests' => $query->paginate(15)->withQueryString()->through(fn (ServiceRequest $serviceRequest): array => $this->row($serviceRequest)),
        ]);
    }

    public function create(TenantContext $context): Response
    {
        $this->authorize('create', ServiceRequest::class);

        $tenant = $context->requireTenant();

        return Inertia::render('tenant/requests/create', [
            'customers' => Customer::query()->forTenant($tenant)->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function store(StoreServiceRequestRequest $request, TenantContext $context, ServiceRequestService $requests): RedirectResponse
    {
        $this->authorize('create', ServiceRequest::class);

        $serviceRequest = $requests->create($context->requireTenant(), $request->validated(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Solicitação registrada.']);

        return to_route('tenant.requests.show', ['serviceRequest' => $serviceRequest->getKey()]);
    }

    public function show(TenantContext $context, int $serviceRequest): Response
    {
        $record = $this->findRequest($context, $serviceRequest);

        $this->authorize('view', $record);

        $record->load(['customer:id,name,phone,whatsapp', 'service:id,name,duration_minutes', 'professional:id,name', 'events.user:id,name', 'appointments.professional:id,name']);

        $tenant = $context->requireTenant();

        return Inertia::render('tenant/requests/show', [
            'request' => [
                ...$this->row($record),
                'description' => $record->description,
                'origin' => $record->origin->label(),
                'closing_reason' => $record->closing_reason,
                'customer_phone' => $record->customer?->whatsapp ?? $record->customer?->phone,
                'service_id' => $record->service_id,
                'priority_value' => $record->priority->value,
                'duration_minutes' => $record->service?->duration_minutes,
                'professional_id' => $record->professional_id,
                'open' => $record->status->isOpen(),
                'can_triage' => in_array($record->status, [RequestStatus::Requested, RequestStatus::Triaged], true),
                'can_schedule' => $record->status === RequestStatus::Triaged && $record->service_id !== null,
                'can_start' => $record->status === RequestStatus::Scheduled,
                'can_complete' => $record->status === RequestStatus::InProgress,
                'can_close_unperformed' => in_array($record->status, [RequestStatus::Scheduled, RequestStatus::InProgress], true),
                'can_cancel' => $record->status->isOpen(),
            ],
            'events' => $record->events->map(fn ($event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'to_status' => $event->to_status ? RequestStatus::from($event->to_status)->label() : null,
                'note' => $event->note,
                'user' => $event->user?->name,
                'at' => $tenant->toLocal($event->created_at)->format('d/m/Y H:i'),
            ])->values()->all(),
            'appointments' => $record->appointments->map(fn ($appointment): array => [
                'id' => $appointment->id,
                'starts_at' => $tenant->toLocal($appointment->starts_at)->format('d/m/Y H:i'),
                'professional' => $appointment->professional?->name,
                'status' => $appointment->status->label(),
            ])->values()->all(),
            'services' => Service::query()->forTenant($record->tenant_id)->where('active', true)->orderBy('name')->get(['id', 'name', 'duration_minutes'])->all(),
            'professionals' => Professional::query()->forTenant($record->tenant_id)->where('active', true)->orderBy('name')
                ->with('services:id')->get(['id', 'name'])
                ->map(fn (Professional $professional): array => [
                    'id' => $professional->id,
                    'name' => $professional->name,
                    'service_ids' => $professional->services->pluck('id')->all(),
                ])->values()->all(),
        ]);
    }

    public function triage(TriageServiceRequestRequest $request, TenantContext $context, ServiceRequestService $requests, int $serviceRequest): RedirectResponse
    {
        $record = $this->findRequest($context, $serviceRequest);

        $this->authorize('manage', $record);

        $requests->triage($record, $request->validated(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Triagem registrada.']);

        return back();
    }

    public function schedule(ScheduleAppointmentRequest $request, TenantContext $context, AppointmentScheduler $scheduler, int $serviceRequest): RedirectResponse
    {
        $record = $this->findRequest($context, $serviceRequest);

        $this->authorize('manage', $record);

        $professional = Professional::query()->forTenant($record->tenant_id)->whereKey($request->integer('professional_id'))->firstOrFail();

        $scheduler->schedule($record, $professional, $request->startsAt($context->requireTenant()), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Atendimento agendado.']);

        return back();
    }

    public function start(Request $request, TenantContext $context, ServiceRequestService $requests, int $serviceRequest): RedirectResponse
    {
        return $this->transition($request, $context, $serviceRequest, fn (ServiceRequest $record) => $requests->start($record, $this->actor($request)), 'Atendimento iniciado.');
    }

    public function complete(Request $request, TenantContext $context, ServiceRequestService $requests, int $serviceRequest): RedirectResponse
    {
        return $this->transition($request, $context, $serviceRequest, fn (ServiceRequest $record) => $requests->complete($record, $this->actor($request)), 'Atendimento concluído.');
    }

    public function notPerformed(ReasonRequest $request, TenantContext $context, ServiceRequestService $requests, int $serviceRequest): RedirectResponse
    {
        return $this->transition($request, $context, $serviceRequest, fn (ServiceRequest $record) => $requests->markNotPerformed($record, $request->string('reason')->value(), $this->actor($request)), 'Atendimento registrado como não realizado.');
    }

    public function cancel(ReasonRequest $request, TenantContext $context, ServiceRequestService $requests, int $serviceRequest): RedirectResponse
    {
        return $this->transition($request, $context, $serviceRequest, fn (ServiceRequest $record) => $requests->cancel($record, $request->string('reason')->value(), $this->actor($request)), 'Solicitação cancelada.');
    }

    /**
     * @param  \Closure(ServiceRequest): mixed  $action
     */
    private function transition(Request $request, TenantContext $context, int $serviceRequest, \Closure $action, string $message): RedirectResponse
    {
        $record = $this->findRequest($context, $serviceRequest);

        $this->authorize('manage', $record);

        $action($record);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ServiceRequest $serviceRequest): array
    {
        return [
            'id' => $serviceRequest->id,
            'customer' => $serviceRequest->customer?->name,
            'service' => $serviceRequest->service?->name,
            'priority' => $serviceRequest->priority->label(),
            'status' => $serviceRequest->status->value,
            'status_label' => $serviceRequest->status->label(),
            'created_at' => $serviceRequest->created_at->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(RequestStatus::cases())
            ->map(fn (RequestStatus $status): array => ['value' => $status->value, 'label' => $status->label()])
            ->values()
            ->all();
    }

    private function findRequest(TenantContext $context, int $serviceRequestId): ServiceRequest
    {
        return ServiceRequest::query()->forTenant($context->requireTenant())->whereKey($serviceRequestId)->firstOrFail();
    }
}
