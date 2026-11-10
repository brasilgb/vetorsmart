<?php

namespace App\Http\Controllers\Api\V1\Professionals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Professionals\SyncProfessionalAvailabilityRequest;
use App\Http\Resources\Api\V1\ProfessionalAvailabilityResource;
use App\Http\Resources\Api\V1\ProfessionalResource;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Services\ProfessionalAvailabilityService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfessionalAvailabilityController extends Controller
{
    /**
     * List the working windows of one professional of the active tenant.
     */
    public function index(TenantContext $context, int $professional): AnonymousResourceCollection
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('view', $record);

        return ProfessionalAvailabilityResource::collection(
            ProfessionalAvailability::query()->where('professional_id', $record->getKey())->orderBy('weekday')->orderBy('start_time')->get(),
        );
    }

    /**
     * Replace the working windows of one professional.
     */
    public function sync(SyncProfessionalAvailabilityRequest $request, TenantContext $context, ProfessionalAvailabilityService $availability, int $professional): ProfessionalResource
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('syncServices', $record);

        return new ProfessionalResource($availability->replace($record, $request->validated('windows'), $this->actor($request)));
    }

    private function findProfessional(TenantContext $context, int $professionalId): Professional
    {
        return Professional::query()
            ->forTenant($context->requireTenant())
            ->whereKey($professionalId)
            ->firstOrFail();
    }
}
