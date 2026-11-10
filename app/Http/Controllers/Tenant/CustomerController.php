<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\IndexCustomerRequest;
use App\Http\Requests\Api\V1\Customers\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function edit(TenantContext $context, int $customer): Response
    {
        $record = $this->findCustomer($context, $customer);

        $this->authorize('update', $record);

        return Inertia::render('tenant/customers/form', ['customer' => $this->form($record)]);
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
