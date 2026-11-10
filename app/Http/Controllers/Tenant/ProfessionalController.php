<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Professionals\StoreProfessionalRequest;
use App\Http\Requests\Api\V1\Professionals\UpdateProfessionalRequest;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Services\ProfessionalAvailabilityService;
use App\Services\ProfessionalRecordService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', Professional::class);

        $query = Professional::query()->forTenant($context->requireTenant())->orderBy('name')->orderBy('id');

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $term = $request->string('q')->trim()->value();

        if ($term !== '') {
            $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
            $digits = preg_replace('/\D+/', '', $term) ?? '';

            $query->where(function ($inner) use ($like, $digits): void {
                $inner->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(email) LIKE ?', [$like]);

                if ($digits !== '') {
                    $inner->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
        }

        return Inertia::render('tenant/professionals/index', [
            'filters' => $request->only(['q', 'active']),
            'professionals' => $query->paginate(15)->withQueryString()->through(fn (Professional $professional): array => [
                'id' => $professional->id,
                'name' => $professional->name,
                'phone' => $professional->phone,
                'email' => $professional->email,
                'active' => $professional->active,
                'services' => $professional->services()->count(),
            ]),
        ]);
    }

    public function create(TenantContext $context): Response
    {
        $this->authorize('create', Professional::class);

        return Inertia::render('tenant/professionals/form', [
            'professional' => null,
            'services' => $this->activeServices($context),
        ]);
    }

    public function store(StoreProfessionalRequest $request, TenantContext $context, ProfessionalRecordService $professionals, ProfessionalAvailabilityService $availability): RedirectResponse
    {
        $this->authorize('create', Professional::class);
        $this->validateLinks($request);

        $tenant = $context->requireTenant();
        $actor = $this->actor($request);

        DB::transaction(function () use ($request, $tenant, $actor, $professionals, $availability): void {
            $professional = $professionals->create($tenant, $request->validated(), $actor);
            $professionals->syncServices($professional, $this->serviceIds($request), $actor);
            $availability->replace($professional, $this->windows($request), $actor);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional cadastrado.']);

        return to_route('tenant.professionals.index');
    }

    public function edit(TenantContext $context, int $professional): Response
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('update', $record);

        return Inertia::render('tenant/professionals/form', [
            'professional' => [
                'id' => $record->id,
                'name' => $record->name,
                'email' => $record->email,
                'phone' => $record->phone,
                'notes' => $record->notes,
                'active' => $record->active,
                'service_ids' => $record->services()->pluck('services.id')->map(fn ($id): int => (int) $id)->values()->all(),
                'windows' => ProfessionalAvailability::query()
                    ->where('professional_id', $record->getKey())
                    ->orderBy('weekday')->orderBy('start_time')
                    ->get(['weekday', 'start_time', 'end_time'])
                    ->map(fn (ProfessionalAvailability $window): array => [
                        'weekday' => $window->weekday,
                        'start_time' => substr((string) $window->start_time, 0, 5),
                        'end_time' => substr((string) $window->end_time, 0, 5),
                    ])
                    ->values()
                    ->all(),
            ],
            'services' => $this->activeServices($context),
        ]);
    }

    public function update(UpdateProfessionalRequest $request, TenantContext $context, ProfessionalRecordService $professionals, ProfessionalAvailabilityService $availability, int $professional): RedirectResponse
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('update', $record);
        $this->validateLinks($request);

        $actor = $this->actor($request);

        DB::transaction(function () use ($request, $record, $actor, $professionals, $availability): void {
            $professionals->update($record, $request->validated(), $actor);

            if ($request->has('service_ids')) {
                $professionals->syncServices($record, $this->serviceIds($request), $actor);
            }

            if ($request->has('windows')) {
                $availability->replace($record, $this->windows($request), $actor);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional atualizado.']);

        return to_route('tenant.professionals.index');
    }

    public function deactivate(Request $request, TenantContext $context, ProfessionalRecordService $professionals, int $professional): RedirectResponse
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('deactivate', $record);

        $professionals->setActive($record, false, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional desativado.']);

        return to_route('tenant.professionals.index');
    }

    public function activate(Request $request, TenantContext $context, ProfessionalRecordService $professionals, int $professional): RedirectResponse
    {
        $record = $this->findProfessional($context, $professional);

        $this->authorize('update', $record);

        $professionals->setActive($record, true, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profissional reativado.']);

        return to_route('tenant.professionals.index');
    }

    /**
     * Validate the linked services and working windows sent with the form.
     *
     * @throws ValidationException
     */
    private function validateLinks(Request $request): void
    {
        Validator::make($request->all(), [
            'service_ids' => ['sometimes', 'array', 'max:200'],
            'service_ids.*' => ['integer', 'distinct'],
            'windows' => ['sometimes', 'array', 'max:70'],
            'windows.*.weekday' => ['required', 'integer', 'between:0,6'],
            'windows.*.start_time' => ['required', 'date_format:H:i'],
            'windows.*.end_time' => ['required', 'date_format:H:i'],
        ])->validate();
    }

    /**
     * @return list<int>
     */
    private function serviceIds(Request $request): array
    {
        return array_values(array_map('intval', (array) $request->input('service_ids', [])));
    }

    /**
     * @return list<array{weekday: int, start_time: string, end_time: string}>
     */
    private function windows(Request $request): array
    {
        return array_values(array_map(fn (array $window): array => [
            'weekday' => (int) $window['weekday'],
            'start_time' => (string) $window['start_time'],
            'end_time' => (string) $window['end_time'],
        ], (array) $request->input('windows', [])));
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function activeServices(TenantContext $context): array
    {
        return Service::query()
            ->forTenant($context->requireTenant())
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Service $service): array => ['id' => $service->id, 'name' => $service->name])
            ->values()
            ->all();
    }

    private function findProfessional(TenantContext $context, int $professionalId): Professional
    {
        return Professional::query()->forTenant($context->requireTenant())->whereKey($professionalId)->firstOrFail();
    }
}
