<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\MembershipStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RootAdmin\UpdateTenantStatusRequest;
use App\Models\AssistantSetting;
use App\Models\AuditLog;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantUser;
use App\Models\TenantWebsite;
use App\Models\User;
use App\Services\TenantStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * List companies with filters. The owner is loaded in the same query, not per row.
     */
    public function index(Request $request): Response
    {
        $query = Tenant::query()
            ->with(['segment:id,name', 'owners.user:id,name,email'])
            ->orderByDesc('id');

        $term = $request->string('q')->trim()->value();

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('slug', 'like', $like));
        }

        if ($request->filled('status') && in_array($request->string('status')->value(), ['active', 'suspended', 'cancelled'], true)) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('segment_id')) {
            $query->where('segment_id', $request->integer('segment_id'));
        }

        $tenants = $query->paginate(15)->withQueryString();

        return Inertia::render('rootadmin/tenants/index', [
            'filters' => $request->only(['q', 'status', 'segment_id']),
            'segments' => Segment::query()->orderBy('name')->get(['id', 'name']),
            'tenants' => $tenants->through(fn (Tenant $tenant): array => $this->summary($tenant)),
        ]);
    }

    /**
     * Show a company with its administrative data and linked users (no credentials).
     */
    public function show(Tenant $tenant): Response
    {
        $tenant->load(['segment:id,name', 'settings']);

        $memberships = TenantUser::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('user:id,name,email,status')
            ->orderBy('id')
            ->paginate(20, ['*'], 'users');

        $activeUsers = TenantUser::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', MembershipStatus::Active)
            ->count();

        $primaryDomain = TenantDomain::query()
            ->forTenant($tenant)
            ->where('is_primary', true)
            ->first(['hostname', 'kind', 'status']);

        $website = TenantWebsite::query()
            ->where('tenant_id', $tenant->getKey())
            ->first(['name', 'status']);

        $assistant = AssistantSetting::query()
            ->forTenant($tenant)
            ->first(['enabled']);

        $history = AuditLog::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('user:id,name')
            ->latest('id')
            ->limit(20)
            ->get(['id', 'action', 'user_id', 'created_at']);

        return Inertia::render('rootadmin/tenants/show', [
            'tenant' => [
                ...$this->summary($tenant),
                'document' => $tenant->document,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'timezone' => $tenant->timezone,
            ],
            'operations' => [
                'active_users' => $activeUsers,
                'primary_domain' => $primaryDomain === null ? null : [
                    'hostname' => $primaryDomain->hostname,
                    'kind' => $primaryDomain->kind->value,
                    'status' => $primaryDomain->status->value,
                ],
                'website' => $website === null ? null : [
                    'name' => $website->name,
                    'status' => $website->status->value,
                ],
                'assistant_enabled' => (bool) ($assistant?->enabled ?? false),
            ],
            'history' => $history->map(fn (AuditLog $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action,
                'user' => $entry->user?->name,
                'created_at' => $entry->created_at?->format('d/m/Y H:i'),
            ]),
            'settings' => $tenant->settings === null ? null : [
                'business_name' => $tenant->settings->business_name,
                'description' => $tenant->settings->description,
                'locale' => $tenant->settings->locale,
                'service_modes' => $tenant->settings->service_modes ?? [],
                'confirmation_policy' => $tenant->settings->confirmation_policy->value,
            ],
            'users' => $memberships->through(fn (TenantUser $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'role' => $membership->role->value,
                'membership_status' => $membership->status->value,
                'user_status' => $membership->user?->status->value,
            ]),
        ]);
    }

    /**
     * Activate or suspend a company. Suspension needs `confirm`; both actions are audited.
     */
    public function updateStatus(UpdateTenantStatusRequest $request, Tenant $tenant, TenantStatusService $statuses): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $target = TenantStatus::from($request->string('status')->value());

        $statuses->change($tenant, $target, $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $target === TenantStatus::Active ? 'Empresa ativada.' : 'Empresa suspensa.',
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Tenant $tenant): array
    {
        $owner = $tenant->owners->first();

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'segment' => $tenant->segment?->name,
            'segment_id' => $tenant->segment_id,
            'owner' => $owner?->user === null ? null : ['name' => $owner->user->name, 'email' => $owner->user->email],
            'status' => $tenant->status->value,
            'created_at' => $tenant->created_at?->toDateString(),
        ];
    }
}
