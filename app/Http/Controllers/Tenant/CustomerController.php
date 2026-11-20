<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AppointmentStatus;
use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\IndexCustomerRequest;
use App\Http\Requests\Api\V1\Customers\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customers\UpdateCustomerRequest;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Services\Customers\CustomerPortfolio;
use App\Services\CustomerService;
use App\Services\Tenancy\TenantContext;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Customer portfolio: search, status and relationship filters (30, 60 or 90 days), with the figures of each
     * customer read from its requests (CustomerPortfolio). Recorded values are shown only to who sees the company's
     * financial figures (owners and admins, as in Meu Crescimento).
     */
    public function index(IndexCustomerRequest $request, TenantContext $context, CustomerPortfolio $portfolio): Response
    {
        $this->authorize('viewAny', Customer::class);

        $tenant = $context->requireTenant();
        $days = in_array($request->integer('dias'), CustomerPortfolio::PERIODS, true) ? $request->integer('dias') : CustomerPortfolio::PERIODS[0];
        $segment = array_key_exists($request->string('relacao')->value(), CustomerPortfolio::SEGMENTS) ? $request->string('relacao')->value() : '';
        $financial = Gate::allows('viewGrowth', $tenant);

        $query = $portfolio->withFigures(Customer::query()->forTenant($tenant))->orderBy('name')->orderBy('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $term = $request->string('q')->trim()->value();

        if ($term !== '') {
            $query->search($term);
        }

        if ($segment !== '') {
            $portfolio->segment($query, $segment, $days);
        }

        $since = now()->subDays($days);

        return Inertia::render('tenant/customers/index', [
            'filters' => [...$request->only(['q', 'status']), 'relacao' => $segment, 'dias' => $days],
            'periods' => CustomerPortfolio::PERIODS,
            'segments' => array_map(fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys(CustomerPortfolio::SEGMENTS), CustomerPortfolio::SEGMENTS),
            'indicators' => $portfolio->indicators((int) $tenant->getKey(), $days),
            'financial' => $financial,
            'customers' => $query->paginate(15)->withQueryString()->through(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'city' => $customer->city,
                'status' => $customer->status->value,
                'completed' => (int) $customer->getAttribute('completed_count'),
                'recurring' => (int) $customer->getAttribute('completed_count') >= CustomerPortfolio::RECURRING_FROM,
                'new' => $customer->created_at !== null && $customer->created_at->greaterThanOrEqualTo($since),
                'last_service' => $customer->getAttribute('last_completed_at') ? $tenant->toLocal(CarbonImmutable::parse((string) $customer->getAttribute('last_completed_at'), 'UTC'))->format('d/m/Y') : null,
                'open' => (int) $customer->getAttribute('open_count'),
                'next_appointment' => $customer->getAttribute('next_appointment_at') ? $tenant->toLocal(CarbonImmutable::parse((string) $customer->getAttribute('next_appointment_at'), 'UTC'))->format('d/m H:i') : null,
                'revenue' => $financial && (int) $customer->getAttribute('revenue_known') > 0 ? Money::format((int) $customer->getAttribute('revenue_cents')) : null,
            ]),
        ]);
    }

    /**
     * The customer's page: identification, contact, commercial summary, what is scheduled ahead and the history of
     * requests. Values are the amounts recorded on completed services (never cancelled ones), shown only to who sees
     * the company's financial figures.
     */
    public function show(Request $request, TenantContext $context, CustomerPortfolio $portfolio, int $customer): Response
    {
        $record = $this->findCustomer($context, $customer);

        $this->authorize('view', $record);

        $tenant = $context->requireTenant();
        $summary = $portfolio->summary($record);
        $financial = Gate::allows('viewGrowth', $tenant);

        return Inertia::render('tenant/customers/show', [
            'customer' => [
                ...$this->form($record),
                'first_contact' => $record->created_at === null ? null : $tenant->toLocal($record->created_at)->format('d/m/Y'),
                'contact' => $this->contactLinks($record),
            ],
            'summary' => [
                ...$summary,
                'revenue' => $financial ? $summary['revenue'] : null,
                'last_service' => $summary['last_service'] === null ? null : ['date' => $tenant->toLocal($summary['last_service']['date'])->format('d/m/Y'), 'service' => $summary['last_service']['service']],
            ],
            'financial' => $financial,
            'upcoming' => Appointment::query()
                ->forTenant($tenant)
                ->where('status', AppointmentStatus::Scheduled->value)
                ->where('starts_at', '>=', now())
                ->whereHas('serviceRequest', fn ($request) => $request->where('customer_id', $record->getKey()))
                ->with(['professional:id,name', 'serviceRequest.service:id,name'])
                ->orderBy('starts_at')
                ->limit(5)
                ->get()
                ->map(fn (Appointment $appointment): array => [
                    'id' => $appointment->id,
                    'request_id' => $appointment->service_request_id,
                    'starts_at' => $tenant->toLocal($appointment->starts_at)->format('d/m/Y H:i'),
                    'service' => $appointment->serviceRequest?->service?->name,
                    'professional' => $appointment->professional?->name,
                ])
                ->values()
                ->all(),
            'history' => $this->history($request, $context, $record),
        ]);
    }

    /**
     * Contact links built from the data already registered (no provider, no automatic sending): phone call, WhatsApp
     * chat link and e-mail. The stored digits are kept as they are; the links only add the country code.
     *
     * @return list<array{kind: string, label: string, href: string}>
     */
    private function contactLinks(Customer $customer): array
    {
        $international = fn (?string $digits): ?string => $digits === null || $digits === '' ? null : (strlen($digits) <= 11 ? '55'.$digits : $digits);
        // A WhatsApp number is a phone too: it is used for the call when no other phone was registered.
        $phone = $international($customer->phone ?: $customer->whatsapp);
        $whatsapp = $international($customer->whatsapp ?: $customer->phone);

        return array_values(array_filter([
            $phone === null ? null : ['kind' => 'phone', 'label' => 'Ligar', 'href' => 'tel:+'.$phone],
            $whatsapp === null ? null : ['kind' => 'whatsapp', 'label' => 'WhatsApp', 'href' => 'https://wa.me/'.$whatsapp],
            blank($customer->email) ? null : ['kind' => 'email', 'label' => 'E-mail', 'href' => 'mailto:'.$customer->email],
        ]));
    }

    public function create(): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('tenant/customers/form', ['customer' => null]);
    }

    public function store(StoreCustomerRequest $request, TenantContext $context, CustomerService $customers): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customers->create($context->requireTenant(), $request->validated(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente cadastrado.']);

        return to_route('tenant.customers.index');
    }

    public function edit(Request $request, TenantContext $context, int $customer): Response
    {
        $record = $this->findCustomer($context, $customer);

        $this->authorize('update', $record);

        return Inertia::render('tenant/customers/form', [
            'customer' => $this->form($record),
            'history' => $this->history($request, $context, $record),
        ]);
    }

    /**
     * Requests of this customer with their appointments, cancellations and last history note.
     * Read from the existing records (no copies), most recent activity first, 10 per page.
     *
     * @return LengthAwarePaginator<int, array{id: int, service: string|null, priority: string, status: string, professional: string|null, created_at: string, closed_at: string|null, closing_reason: string|null, appointments: array<int, array{starts_at: string, professional: string|null, status: string, cancelling_reason: string|null, cancelled_by: string|null, cancelled_at: string|null}>, last_event: array{note: string|null, user: string|null, at: string}|null}>
     */
    private function history(Request $request, TenantContext $context, Customer $customer): LengthAwarePaginator
    {
        $tenant = $context->requireTenant();

        return ServiceRequest::query()
            ->forTenant($tenant)
            ->where('customer_id', $customer->getKey())
            ->with([
                'service:id,name',
                'professional:id,name',
                'appointments.professional:id,name',
                'appointments.cancelledBy:id,name',
                'events.user:id,name',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'historico')
            ->withQueryString()
            ->through(fn (ServiceRequest $serviceRequest): array => [
                'id' => $serviceRequest->id,
                'service' => $serviceRequest->service?->name,
                'priority' => $serviceRequest->priority->label(),
                'status' => $serviceRequest->status->label(),
                'professional' => $serviceRequest->professional?->name,
                'created_at' => $tenant->toLocal($serviceRequest->created_at)->format('d/m/Y H:i'),
                'closed_at' => $serviceRequest->closed_at ? $tenant->toLocal($serviceRequest->closed_at)->format('d/m/Y H:i') : null,
                'closing_reason' => $serviceRequest->closing_reason,
                'appointments' => $serviceRequest->appointments->map(fn ($appointment): array => [
                    'starts_at' => $tenant->toLocal($appointment->starts_at)->format('d/m/Y H:i'),
                    'professional' => $appointment->professional?->name,
                    'status' => $appointment->status->label(),
                    'cancelling_reason' => $appointment->cancelling_reason,
                    'cancelled_by' => $appointment->cancelledBy?->name,
                    'cancelled_at' => $appointment->cancelled_at ? $tenant->toLocal($appointment->cancelled_at)->format('d/m/Y H:i') : null,
                ])->values()->all(),
                'last_event' => $serviceRequest->events->last() ? [
                    'note' => $serviceRequest->events->last()->note,
                    'user' => $serviceRequest->events->last()->user?->name,
                    'at' => $tenant->toLocal($serviceRequest->events->last()->created_at)->format('d/m/Y H:i'),
                ] : null,
            ]);
    }

    public function update(UpdateCustomerRequest $request, TenantContext $context, CustomerService $customers, int $customer): RedirectResponse
    {
        $record = $this->findCustomer($context, $customer);

        $this->authorize('update', $record);

        $customers->update($record, $request->validated(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente atualizado.']);

        return to_route('tenant.customers.index');
    }

    /**
     * Archive instead of deleting: the record keeps its history.
     */
    public function archive(Request $request, TenantContext $context, CustomerService $customers, int $customer): RedirectResponse
    {
        $record = $this->findCustomer($context, $customer);

        $this->authorize('deactivate', $record);

        $customers->deactivate($record, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente arquivado.']);

        return to_route('tenant.customers.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function form(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'person_type' => $customer->person_type->value,
            'document' => $customer->document,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'whatsapp' => $customer->whatsapp,
            'address' => $customer->address,
            'city' => $customer->city,
            'state' => $customer->state,
            'notes' => $customer->notes,
            'status' => $customer->status === CustomerStatus::Active ? 'active' : 'inactive',
        ];
    }

    private function findCustomer(TenantContext $context, int $customerId): Customer
    {
        return Customer::query()->forTenant($context->requireTenant())->whereKey($customerId)->firstOrFail();
    }
}
