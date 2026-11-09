<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\StoreServiceCategoryRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateServiceCategoryRequest;
use App\Http\Resources\Api\V1\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Services\ServiceCategoryService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceCategoryController extends Controller
{
    /**
     * List the tenant's categories in display order.
     */
    public function index(Request $request, TenantContext $context): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceCategory::class);

        $query = ServiceCategory::query()
            ->forTenant($context->requireTenant())
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return ServiceCategoryResource::collection($query->paginate(50));
    }

    public function store(StoreServiceCategoryRequest $request, TenantContext $context, ServiceCategoryService $categories): ServiceCategoryResource
    {
        $this->authorize('create', ServiceCategory::class);

        return new ServiceCategoryResource($categories->create($context->requireTenant(), $request->validated(), $this->actor($request)));
    }

    public function show(TenantContext $context, int $category): ServiceCategoryResource
    {
        $record = $this->findCategory($context, $category);

        $this->authorize('view', $record);

        return new ServiceCategoryResource($record);
    }

    public function update(UpdateServiceCategoryRequest $request, TenantContext $context, ServiceCategoryService $categories, int $category): ServiceCategoryResource
    {
        $record = $this->findCategory($context, $category);

        $this->authorize('update', $record);

        return new ServiceCategoryResource($categories->update($record, $request->validated(), $this->actor($request)));
    }

    /**
     * Deactivate a category. Services that already use it keep their link.
     */
    public function destroy(Request $request, TenantContext $context, ServiceCategoryService $categories, int $category): ServiceCategoryResource
    {
        $record = $this->findCategory($context, $category);

        $this->authorize('deactivate', $record);

        return new ServiceCategoryResource($categories->deactivate($record, $this->actor($request)));
    }

    private function findCategory(TenantContext $context, int $categoryId): ServiceCategory
    {
        return ServiceCategory::query()
            ->forTenant($context->requireTenant())
            ->whereKey($categoryId)
            ->firstOrFail();
    }
}
