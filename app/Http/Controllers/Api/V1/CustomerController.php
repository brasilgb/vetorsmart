<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customers\IndexCustomerRequest;
use App\Http\Requests\Api\V1\Customers\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customers\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * List the active tenant's customers. Supports `q` (name, e-mail or phone), `status` and `per_page`.
     */
    public function index(IndexCustomerRequest $request, TenantContext $context): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()
            ->forTenant($context->requireTenant())
            ->orderBy('name')
            ->orderBy('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $term = $request->string('q')->trim()->value();

        if ($term !== '') {
            $query->search($term);
        }

        return CustomerResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    /**
     * Register a customer. Only the name is required.
     */
    public function store(StoreCustomerRequest $request, TenantContext $context, CustomerService $customers): CustomerResource
    {
        $this->authorize('create', Customer::class);

        $customer = $customers->create($context->requireTenant(), $request->validated(), $this->actor($request));

        return new CustomerResource($customer);
    }

    /**
     * Show one customer of the active tenant.
     */
    public function show(TenantContext $context, int $customer): CustomerResource
    {
        $record = $this->findCustomer($context, $customer);

        $this->authorize('view', $record);

        return new CustomerResource($record);
    }

    /**
     * Update a customer of the active tenant.
     */
    public function update(UpdateCustomerRequest $request, TenantContext $context, CustomerService $customers, int $customer): CustomerResource
    {
        $record = $this->findCustomer($context, $customer);

        $this->authorize('update', $record);

        return new CustomerResource($customers->update($record, $request->validated(), $this->actor($request)));
    }

    /**
     * Deactivate a customer. The record is kept for history.
     */
    public function destroy(Request $request, TenantContext $context, CustomerService $customers, int $customer): CustomerResource
    {
        $record = $this->findCustomer($context, $customer);

        $this->authorize('deactivate', $record);

        return new CustomerResource($customers->deactivate($record, $this->actor($request)));
    }

    /**
     * Find a customer only inside the active tenant. Anything else is a 404.
     */
    private function findCustomer(TenantContext $context, int $customerId): Customer
    {
        return Customer::query()
            ->forTenant($context->requireTenant())
            ->whereKey($customerId)
            ->firstOrFail();
    }
}
