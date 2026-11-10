<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Tenancy\TenantResolver;
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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{id: int, name: string, role: string}|null
     */
    private function activeTenant(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $membership = app(TenantResolver::class)->resolve($user, $request->session()->get('current_tenant_id'));

        if ($membership === null) {
            return null;
        }

        return [
            'id' => $membership->tenant_id,
            'name' => $membership->tenant->name,
            'role' => $membership->role->value,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, role: string}>
     */
    private function switchableTenants(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [];
        }

        return app(TenantResolver::class)
            ->accessibleFor($user)
            ->map(fn ($membership): array => [
                'id' => $membership->tenant_id,
                'name' => $membership->tenant->name,
                'role' => $membership->role->value,
            ])
            ->values()
            ->all();
    }
}
