<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\IndexServiceRequest;
use App\Http\Requests\Api\V1\Catalog\StoreServiceRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateServiceRequest;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\ServiceService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const array SORTABLE = ['name' => 'name', 'sort_order' => 'sort_order', 'price' => 'price', 'created_at' => 'created_at'];

    public function index(IndexServiceRequest $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', Service::class);

        $query = Service::query()->forTenant($context->requireTenant())->with('category');

        $term = $request->string('q')->trim()->value();

        if ($term !== '') {
            $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        if ($request->filled('category_id')) {
            $query->where('service_category_id', $request->integer('category_id'));
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $sort = self::SORTABLE[$request->string('sort', 'sort_order')->value()] ?? 'sort_order';
        $direction = $request->string('direction', 'asc')->value() === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $direction)->orderBy('name')->orderBy('id');

        return Inertia::render('tenant/services/index', [
            'filters' => $request->only(['q', 'category_id', 'active', 'sort', 'direction']),
            'categories' => $this->categories($context),
            'services' => $query->paginate(15)->withQueryString()->through(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category?->name,
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
                'price_type' => $service->price_type->value,
                'active' => $service->active,
            ]),
        ]);
    }

    public function create(TenantContext $context): Response
    {
        $this->authorize('create', Service::class);

        return Inertia::render('tenant/services/form', ['service' => null, 'categories' => $this->categories($context)]);
    }

    public function store(StoreServiceRequest $request, TenantContext $context, ServiceService $services): RedirectResponse
    {
        $this->authorize('create', Service::class);

        $services->create($context->requireTenant(), $request->validated(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço cadastrado.']);

        return to_route('tenant.services.index');
    }

    public function edit(TenantContext $context, int $service): Response
    {
        $record = $this->findService($context, $service);

        $this->authorize('update', $record);

        return Inertia::render('tenant/services/form', [
            'service' => [
                'id' => $record->id,
                'name' => $record->name,
                'description' => $record->description,
                'service_category_id' => $record->service_category_id,
                'duration_minutes' => $record->duration_minutes,
                'price' => $record->price,
                'price_type' => $record->price_type->value,
                'requires_evaluation' => $record->requires_evaluation,
                'active' => $record->active,
                'sort_order' => $record->sort_order,
            ],
            'categories' => $this->categories($context),
        ]);
    }

    public function update(UpdateServiceRequest $request, TenantContext $context, ServiceService $services, int $service): RedirectResponse
    {
        $record = $this->findService($context, $service);

        $this->authorize('update', $record);

        $services->update($record, $request->validated(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço atualizado.']);

        return to_route('tenant.services.index');
    }

    /**
     * Deactivate instead of deleting: past services keep their history.
     */
    public function deactivate(Request $request, TenantContext $context, ServiceService $services, int $service): RedirectResponse
    {
        $record = $this->findService($context, $service);

        $this->authorize('deactivate', $record);

        $services->deactivate($record, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serviço desativado.']);

        return to_route('tenant.services.index');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categories(TenantContext $context): array
    {
        return ServiceCategory::query()
            ->forTenant($context->requireTenant())
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ServiceCategory $category): array => ['id' => $category->id, 'name' => $category->name])
            ->values()
            ->all();
    }

    private function findService(TenantContext $context, int $serviceId): Service
    {
        return Service::query()->forTenant($context->requireTenant())->whereKey($serviceId)->firstOrFail();
    }
}
