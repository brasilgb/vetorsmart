<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\IndexCustomerRequest;
use App\Http\Requests\Api\V1\Customers\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Services\CustomerService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(IndexCustomerRequest $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()->forTenant($context->requireTenant())->orderBy('name')->orderBy('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $term = $request->string('q')->trim()->value();

        if ($term !== '') {
            $query->search($term);
        }

        return Inertia::render('tenant/customers/index', [
            'filters' => $request->only(['q', 'status']),
            'customers' => $query->paginate(15)->withQueryString()->through(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'city' => $customer->city,
                'status' => $customer->status->value,
            ]),
        ]);
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
     * @return LengthAwarePaginator<int, array<string, mixed>>
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
