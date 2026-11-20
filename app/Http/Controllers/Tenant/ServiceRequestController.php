<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CustomerStatus;
use App\Enums\MembershipStatus;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Operations\AssignServiceRequestRequest;
use App\Http\Requests\Tenant\Operations\RealizedAmountRequest;
use App\Http\Requests\Tenant\Operations\ReasonRequest;
use App\Http\Requests\Tenant\Operations\ScheduleAppointmentRequest;
use App\Http\Requests\Tenant\Operations\StoreServiceRequestRequest;
use App\Http\Requests\Tenant\Operations\TriageServiceRequestRequest;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AppointmentScheduler;
use App\Services\ServiceRequestService;
use App\Services\Tenancy\TenantContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    /**
     * Customers offered at once in the new request form. A larger list is reached by searching.
     */
    private const int CUSTOMER_OPTIONS = 50;

    /**
     * Status filter for requests closed without the service (cancelled or not performed).
     */
    private const string CLOSED_WITHOUT_SERVICE = 'sem-servico';

    public function index(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', ServiceRequest::class);

        $query = ServiceRequest::query()
            ->forTenant($context->requireTenant())
            ->with(['customer:id,name', 'service:id,name', 'tenant:id,timezone', 'assignee:id,name'])
            ->latest('id');

        $status = $request->string('status')->value();

        match (true) {
            RequestStatus::tryFrom($status) !== null => $query->where('status', $status),
            // Closed without the service: cancelled or not performed (Meu Crescimento counts them together).
            $status === self::CLOSED_WITHOUT_SERVICE => $query->whereIn('status', [RequestStatus::Cancelled->value, RequestStatus::NotPerformed->value]),
            default => $status = '',
        };

        // Completed services whose amount was not informed, and open requests without recent movement.
        $withoutAmount = $request->boolean('sem_valor');

        if ($withoutAmount) {
            $query->where('status', RequestStatus::Completed->value)->whereNull('realized_amount_cents');
        }

        $stalled = $request->boolean('parada');

        if ($stalled) {
            $query->stalled();
        }

        $origin = $request->string('origem')->value();

        if (RequestOrigin::tryFrom($origin) !== null) {
            $query->where('origin', $origin);
        }

        $tenant = $context->requireTenant();
        $assignees = $this->assignees($tenant->id);
        $responsible = $request->string('responsavel')->value();

        match (true) {
            $responsible === 'nenhum' => $query->whereNull('assigned_user_id'),
            $responsible === 'meus' => $query->where('assigned_user_id', $request->user()?->getKey()),
            ctype_digit($responsible) && collect($assignees)->contains('id', (int) $responsible) => $query->where('assigned_user_id', (int) $responsible),
            default => $responsible = '',
        };

        // Period in the company's own calendar: of arrival (default) or of closing ("data=encerramento").
        $from = $this->localDate($request->string('de')->value());
        $until = $this->localDate($request->string('ate')->value());
        $dateField = $request->string('data')->value() === 'encerramento' ? 'encerramento' : 'recebimento';
        $column = $dateField === 'encerramento' ? 'closed_at' : 'created_at';

        if ($from !== null) {
            $query->where($column, '>=', $tenant->startOfLocalDay($from)->utc());
        }

        if ($until !== null) {
            $query->where($column, '<', $tenant->startOfLocalDay($until)->addDay()->utc());
        }

        return Inertia::render('tenant/requests/index', [
            'filters' => [
                'status' => $status,
                'origem' => RequestOrigin::tryFrom($origin) !== null ? $origin : '',
                'responsavel' => $responsible,
                'de' => $from ?? '',
                'ate' => $until ?? '',
                'data' => $dateField === 'encerramento' ? 'encerramento' : '',
                'sem_valor' => $withoutAmount ? '1' : '',
                'parada' => $stalled ? '1' : '',
            ],
            'assignees' => $assignees,
            'statuses' => $this->statusOptions(),
            'origins' => array_map(fn (RequestOrigin $item): array => ['value' => $item->value, 'label' => $item->label()], RequestOrigin::cases()),
            'requests' => $query->paginate(15)->withQueryString()->through(fn (ServiceRequest $serviceRequest): array => $this->row($serviceRequest)),
        ]);
    }

    /**
     * The customer list is searched on the server and capped, so the page stays small for a company with many
     * customers. The search reloads only the customer props.
     */
    public function create(Request $request, TenantContext $context): Response
    {
        $this->authorize('create', ServiceRequest::class);

        $tenant = $context->requireTenant();
        $term = $request->string('q')->trim()->limit(100, '')->value();

        $query = Customer::query()->forTenant($tenant)->where('status', CustomerStatus::Active->value)->orderBy('name')->orderBy('id');

        if ($term !== '') {
            $query->search($term);
        }

        $customers = $query->limit(self::CUSTOMER_OPTIONS + 1)->get(['id', 'name']);

        return Inertia::render('tenant/requests/create', [
            'filters' => ['q' => $term],
            'customers' => $customers->take(self::CUSTOMER_OPTIONS)->map(fn (Customer $customer): array => ['id' => $customer->id, 'name' => $customer->name])->values()->all(),
            'hasMoreCustomers' => $customers->count() > self::CUSTOMER_OPTIONS,
            'hasCustomers' => $term === '' ? $customers->isNotEmpty() : Customer::query()->forTenant($tenant)->where('status', CustomerStatus::Active->value)->exists(),
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

        $record->load(['assignee:id,name', 'customer:id,name,phone,whatsapp', 'service:id,name,duration_minutes', 'professional:id,name', 'events.user:id,name', 'appointments.professional:id,name']);

        $tenant = $context->requireTenant();

        return Inertia::render('tenant/requests/show', [
            'request' => [
                ...$this->row($record),
                'description' => $record->description,
                'origin' => $record->origin->label(),
                'origin_value' => $record->origin->value,
                'closing_reason' => $record->closing_reason,
                'customer_phone' => $record->customer->whatsapp ?? $record->customer->phone,
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
                'assigned_user_id' => $record->assigned_user_id,
                'can_assign' => $record->status->isOpen() && Gate::allows('assign', $record),
                'realized_amount' => $record->realized_amount_cents === null ? null : Money::format($record->realized_amount_cents),
                'realized_amount_input' => $record->realized_amount_cents === null ? '' : Money::plain($record->realized_amount_cents),
                'realized_amount_recorded_at' => $record->realized_amount_recorded_at === null ? null : $tenant->toLocal($record->realized_amount_recorded_at)->format('d/m/Y H:i'),
                'can_record_amount' => $record->status === RequestStatus::Completed && Gate::allows('recordAmount', $record),
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
                'date' => $tenant->toLocal($appointment->starts_at)->format('Y-m-d'),
                'professional' => $appointment->professional?->name,
                'status' => $appointment->status->label(),
            ])->values()->all(),
            'assignees' => $this->assignees($record->tenant_id),
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

    public function complete(RealizedAmountRequest $request, TenantContext $context, ServiceRequestService $requests, int $serviceRequest): RedirectResponse
    {
        return $this->transition($request, $context, $serviceRequest, fn (ServiceRequest $record) => $requests->complete($record, $this->actor($request), $request->cents()), 'Atendimento concluído.');
    }

    /**
     * Choose or change the member responsible for the request (owners and admins).
     */
    public function assign(AssignServiceRequestRequest $request, TenantContext $context, ServiceRequestService $requests, int $serviceRequest): RedirectResponse
    {
        $record = $this->findRequest($context, $serviceRequest);
        $this->authorize('assign', $record);

        $userId = $request->validated('assigned_user_id');
        $assignee = $userId === null ? null : User::query()->find((int) $userId);

        if ($userId !== null && $assignee === null) {
            throw ValidationException::withMessages(['assigned_user_id' => ['Escolha uma pessoa ativa da equipe desta empresa.']]);
        }

        $requests->assign($record, $assignee, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => $assignee === null ? 'Solicitação sem responsável.' : 'Responsável definido: '.$assignee->name.'.']);

        return back();
    }

    /**
     * Record or correct the realized amount of a completed service.
     */
    public function amount(RealizedAmountRequest $request, TenantContext $context, ServiceRequestService $requests, int $serviceRequest): RedirectResponse
    {
        $record = $this->findRequest($context, $serviceRequest);
        $this->authorize('recordAmount', $record);

        $requests->recordRealizedAmount($record, $request->cents(), $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => $request->cents() === null ? 'Valor realizado marcado como não informado.' : 'Valor realizado registrado.']);

        return back();
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
            'origin' => $serviceRequest->origin->value,
            'origin_label' => $serviceRequest->origin->label(),
            'discovery' => $serviceRequest->discovery?->label(),
            'assignee' => $serviceRequest->assignee?->name,
            'status' => $serviceRequest->status->value,
            'status_label' => $serviceRequest->status->label(),
            'created_at' => $serviceRequest->tenant->toLocal($serviceRequest->created_at)->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ...array_map(
                fn (RequestStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                RequestStatus::cases(),
            ),
            ['value' => self::CLOSED_WITHOUT_SERVICE, 'label' => 'Canceladas ou não realizadas'],
        ];
    }

    /**
     * Active members of the company who can be responsible for a request.
     *
     * @return list<array{id: int, name: string}>
     */
    private function assignees(int $tenantId): array
    {
        return array_values(TenantUser::query()
            ->forTenant($tenantId)
            ->where('status', MembershipStatus::Active->value)
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::Active->value))
            ->with('user:id,name')
            ->get()
            ->map(fn (TenantUser $member): array => ['id' => (int) $member->user_id, 'name' => (string) $member->user?->name])
            ->sortBy('name')
            ->values()
            ->all());
    }

    /**
     * A date typed in a filter (Y-m-d), or null when it is not one.
     */
    private function localDate(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }

    private function findRequest(TenantContext $context, int $serviceRequestId): ServiceRequest
    {
        return ServiceRequest::query()->forTenant($context->requireTenant())->whereKey($serviceRequestId)->firstOrFail();
    }
}
