<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\IndexServiceRequest;
use App\Http\Requests\Api\V1\Catalog\StoreServiceRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateServiceRequest;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Service;
use App\Services\ServiceService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    /**
     * Sortable columns, whitelisted so the client cannot order by arbitrary SQL.
     *
     * @var array<string, string>
     */
    private const array SORTABLE = [
        'name' => 'name',
        'sort_order' => 'sort_order',
        'price' => 'price',
        'created_at' => 'created_at',
    ];

    /**
     * List the tenant's services. Filters: `category_id`, `active`. Sort: `sort` and `direction`.
     */
    public function index(IndexServiceRequest $request, TenantContext $context): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        $query = Service::query()
            ->forTenant($context->requireTenant())
            ->with('category');

        if ($request->filled('category_id')) {
            $query->where('service_category_id', $request->integer('category_id'));
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $sort = self::SORTABLE[$request->string('sort', 'sort_order')->value()] ?? 'sort_order';
        $direction = $request->string('direction', 'asc')->value() === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $direction)->orderBy('name')->orderBy('id');

        return ServiceResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    /**
     * Create a service. Only the name is required.
     */
    public function store(StoreServiceRequest $request, TenantContext $context, ServiceService $services): ServiceResource
    {
        $this->authorize('create', Service::class);

        return new ServiceResource($services->create($context->requireTenant(), $request->validated(), $this->actor($request))->load('category'));
    }

    public function show(TenantContext $context, int $service): ServiceResource
    {
        $record = $this->findService($context, $service);

        $this->authorize('view', $record);

        return new ServiceResource($record->load('category'));
    }

    public function update(UpdateServiceRequest $request, TenantContext $context, ServiceService $services, int $service): ServiceResource
    {
        $record = $this->findService($context, $service);

        $this->authorize('update', $record);

        return new ServiceResource($services->update($record, $request->validated(), $this->actor($request))->load('category'));
    }

    /**
     * Deactivate a service. It keeps its history and stops accepting new appointments.
     */
    public function destroy(Request $request, TenantContext $context, ServiceService $services, int $service): ServiceResource
    {
        $record = $this->findService($context, $service);

        $this->authorize('deactivate', $record);

        return new ServiceResource($services->deactivate($record, $this->actor($request))->load('category'));
    }

    private function findService(TenantContext $context, int $serviceId): Service
    {
        return Service::query()
            ->forTenant($context->requireTenant())
            ->whereKey($serviceId)
            ->firstOrFail();
    }
}
