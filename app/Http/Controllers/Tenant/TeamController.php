<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TenantRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTenantUserRequest;
use App\Http\Requests\Api\V1\UpdateTenantUserRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\Tenancy\TenantContext;
use App\Services\TenantUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Users and permissions of the active company. Every change goes through the
 * same services and policies as the API, including the protection that keeps
 * at least one active owner.
 */
class TeamController extends Controller
{
    public function index(TenantContext $context): Response
    {
        $tenant = $context->requireTenant();

        Gate::authorize('viewUsers', $tenant);

        $memberships = TenantUser::query()
            ->forTenant($tenant)
            ->with('user:id,name,email,status')
            ->orderBy('id')
            ->paginate(15);

        return Inertia::render('tenant/team/index', [
            'memberships' => $memberships->through(fn (TenantUser $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'role' => $membership->role->value,
                'status' => $membership->status->value,
                'is_self' => $membership->user_id === $this->actor(request())->getKey(),
            ]),
            'roles' => $this->assignableRoles($tenant),
        ]);
    }

    public function store(StoreTenantUserRequest $request, TenantContext $context, TenantUserService $users): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $role = TenantRole::from($request->string('role')->value());

        Gate::authorize('viewUsers', $tenant);
        Gate::authorize('assignRole', [$tenant, $role]);

        $users->create(
            tenant: $tenant,
            name: $request->string('name')->value(),
            email: $request->string('email')->value(),
            password: $request->string('password')->value(),
            role: $role,
            actor: $this->actor($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário adicionado à empresa.']);

        return to_route('tenant.team.index');
    }

    public function update(UpdateTenantUserRequest $request, TenantContext $context, TenantUserService $users, int $membership): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $record = $this->findMembership($tenant, $membership);
        $data = $request->validated();

        Gate::authorize('manageUser', [$tenant, $record]);

        if (isset($data['role'])) {
            $data['role'] = TenantRole::from($data['role']);
            Gate::authorize('assignRole', [$tenant, $data['role']]);
        }

        $users->update($record, $data, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Permissões atualizadas.']);

        return to_route('tenant.team.index');
    }

    public function activate(Request $request, TenantContext $context, TenantUserService $users, int $membership): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $record = $this->findMembership($tenant, $membership);

        Gate::authorize('manageUser', [$tenant, $record]);

        $users->activate($record, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acesso reativado.']);

        return to_route('tenant.team.index');
    }

    /**
     * Suspend the access of this company. The user account itself is kept.
     */
    public function deactivate(Request $request, TenantContext $context, TenantUserService $users, int $membership): RedirectResponse
    {
        $tenant = $context->requireTenant();
        $record = $this->findMembership($tenant, $membership);

        Gate::authorize('manageUser', [$tenant, $record]);

        $users->deactivate($record, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acesso suspenso.']);

        return to_route('tenant.team.index');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function assignableRoles(Tenant $tenant): array
    {
        $roles = [
            ['value' => TenantRole::Owner->value, 'label' => 'Proprietário'],
            ['value' => TenantRole::Admin->value, 'label' => 'Administrador'],
            ['value' => TenantRole::Operator->value, 'label' => 'Operador'],
        ];

        return array_values(array_filter($roles, fn (array $role): bool => Gate::forUser($this->actor(request()))->allows('assignRole', [$tenant, TenantRole::from($role['value'])])));
    }

    private function findMembership(Tenant $tenant, int $membershipId): TenantUser
    {
        return TenantUser::query()->forTenant($tenant)->whereKey($membershipId)->firstOrFail();
    }
}
