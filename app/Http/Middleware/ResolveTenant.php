<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's active tenant to the request. The tenant is
 * taken from the server-side session and validated against memberships; the
 * request input is never used to pick a tenant here.
 */
class ResolveTenant
{
    public function __construct(
        private TenantResolver $resolver,
        private TenantContext $context,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $membership = $this->resolver->resolve($user, $request->session()->get('current_tenant_id'));

        if ($membership === null) {
            if (! $request->expectsJson() && $user->isRootAdmin()) {
                return redirect()->route('rootadmin.dashboard');
            }

            return $this->denyWithoutCompany($request);
        }

        $request->session()->put('current_tenant_id', $membership->tenant_id);

        $this->context->set($membership);

        try {
            return $next($request);
        } finally {
            $this->context->forget();
        }
    }

    /**
     * The signed-in user has no active company (suspended company, inactive membership or none at all).
     *
     * Browsers get a page that explains the situation and offers to sign out. An Inertia visit is turned
     * into a full page load of the same address, so that page is not shown inside the error overlay.
     */
    private function denyWithoutCompany(Request $request): Response
    {
        if ($request->expectsJson()) {
            abort(403, 'Nenhuma empresa ativa disponível para este usuário.');
        }

        if ($request->isMethod('GET') && $request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        return response()->view('errors.no-active-company', status: 403);
    }
}
