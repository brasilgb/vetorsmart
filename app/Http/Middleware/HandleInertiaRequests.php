<?php

namespace App\Http\Middleware;

use App\Models\TenantNotification;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Tenant data is lazy and read-only: it is resolved from the session and the
     * user's memberships, and nothing is written here.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'tenant' => fn () => $this->activeTenant($request),
            'tenants' => fn () => $this->switchableTenants($request),
            'unreadNotifications' => fn () => $this->unreadNotifications($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The company the request runs for. A route with tenant.resolve has already bound it to the TenantContext, so it
     * is reused; elsewhere it is picked from the accessible memberships the same way TenantResolver::resolve() does.
     *
     * @return array{id: int, name: string, role: string}|null
     */
    private function activeTenant(Request $request): ?array
    {
        $membership = app(TenantContext::class)->membership();

        if ($membership === null) {
            $memberships = $this->accessibleMemberships($request);
            $preferred = $request->session()->get('current_tenant_id');

            $membership = ($preferred === null ? null : $memberships->firstWhere('tenant_id', (int) $preferred)) ?? $memberships->first();
        }

        return $membership === null ? null : $this->tenantSummary($membership);
    }

    /**
     * @return array<int, array{id: int, name: string, role: string}>
     */
    private function switchableTenants(Request $request): array
    {
        return $this->accessibleMemberships($request)
            ->map(fn (TenantUser $membership): array => $this->tenantSummary($membership))
            ->values()
            ->all();
    }

    /**
     * Active memberships of the signed-in user in active companies, loaded once per request for both shared props.
     *
     * @return Collection<int, TenantUser>
     */
    private function accessibleMemberships(Request $request): Collection
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return new Collection;
        }

        return $request->attributes->get('vs.accessible_memberships')
            ?? tap(app(TenantResolver::class)->accessibleFor($user), fn (Collection $memberships) => $request->attributes->set('vs.accessible_memberships', $memberships));
    }

    /**
     * Unread notices of the signed-in person in the active company, for the badge in the header.
     */
    private function unreadNotifications(Request $request): int
    {
        $user = $request->user();
        $tenant = $this->activeTenant($request);

        if (! $user instanceof User || $tenant === null) {
            return 0;
        }

        return TenantNotification::query()->ownedBy($tenant['id'], $user)->whereNull('read_at')->count();
    }

    /**
     * @return array{id: int, name: string, role: string}
     */
    private function tenantSummary(TenantUser $membership): array
    {
        return [
            'id' => $membership->tenant_id,
            'name' => $membership->tenant->name,
            'role' => $membership->role->value,
        ];
    }
}
