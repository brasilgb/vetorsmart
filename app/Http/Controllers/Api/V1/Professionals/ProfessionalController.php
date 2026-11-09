<?php

namespace App\Http\Controllers\Api\V1\Professionals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Professionals\StoreProfessionalRequest;
use App\Http\Requests\Api\V1\Professionals\SyncProfessionalServicesRequest;
use App\Http\Requests\Api\V1\Professionals\UpdateProfessionalRequest;
use App\Http\Resources\Api\V1\ProfessionalResource;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Professional;
use App\Services\ProfessionalRecordService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfessionalController extends Controller
{
    /**
     * List the tenant's professionals. Filters: `q` (name, e-mail or phone) and `active`.
     */
    public function index(Request $request, TenantContext $context): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Professional::class);

        $query = Professional::query()
            ->forTenant($context->requireTenant())
            ->orderBy('name')
            ->orderBy('id');

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $term = $request->string('q')->trim()->value();

        if ($term !== '') {
            $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
            $digits = preg_replace('/\D+/', '', $term) ?? '';

            $query->where(function ($inner) use ($like, $digits): void {
                $inner->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);

                if ($digits !== '') {
                    $inner->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
        }

        return ProfessionalResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreProfessionalRequest $request, TenantContext $context, ProfessionalRecordService $professionals): ProfessionalResource
    {
        $this->authorize('create', Professional::class);

        return new ProfessionalResource($professionals->create($context->requireTenant(), $request->validated(), $this->actor($request)));
    }

    public function show(TenantContext $context, int $professional): ProfessionalResource
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('view', $record);

        return new ProfessionalResource($record);
    }

    public function update(UpdateProfessionalRequest $request, TenantContext $context, ProfessionalRecordService $professionals, int $professional): ProfessionalResource
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('update', $record);

        return new ProfessionalResource($professionals->update($record, $request->validated(), $this->actor($request)));
    }

    /**
     * Deactivate a professional. The record and its service links are kept.
     */
    public function destroy(Request $request, TenantContext $context, ProfessionalRecordService $professionals, int $professional): ProfessionalResource
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('deactivate', $record);

        return new ProfessionalResource($professionals->setActive($record, false, $this->actor($request)));
    }

    /**
     * Reactivate a professional.
     */
    public function activate(Request $request, TenantContext $context, ProfessionalRecordService $professionals, int $professional): ProfessionalResource
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('update', $record);

        return new ProfessionalResource($professionals->setActive($record, true, $this->actor($request)));
    }

    /**
     * List the services this professional can perform.
     */
    public function services(TenantContext $context, int $professional): AnonymousResourceCollection
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('view', $record);

        return ServiceResource::collection(
            $record->services()->with('category')->orderBy('name')->paginate(50),
        );
    }

    /**
     * Replace the services this professional can perform.
     */
    public function syncServices(SyncProfessionalServicesRequest $request, TenantContext $context, ProfessionalRecordService $professionals, int $professional): ProfessionalResource
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('syncServices', $record);

        $updated = $professionals->syncServices($record, array_map('intval', $request->validated('service_ids')), $this->actor($request));

        return new ProfessionalResource($updated);
    }

    private function findProfessional(TenantContext $context, int $professionalId): Professional
    {
        return Professional::query()
            ->forTenant($context->requireTenant())
            ->whereKey($professionalId)
            ->firstOrFail();
    }
}
