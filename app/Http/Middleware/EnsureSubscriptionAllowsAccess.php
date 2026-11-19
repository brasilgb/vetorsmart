<?php

namespace App\Http\Middleware;

use App\Enums\TenantRole;
use App\Models\Subscription;
use App\Services\Billing\BillingEnforcement;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the panel of a company whose subscription is financially suspended or cancelled. It runs after
 * tenant.resolve and reads only the subscription: the administrative suspension (tenants.status) is handled by
 * the tenant resolver and is a different thing. A company without a subscription (created before billing existed)
 * keeps its access, and while billing is not in force (BillingEnforcement) no company is ever closed. Owners and admins are sent to Minha Assinatura to regularize; operators see an explanation.
 */
class EnsureSubscriptionAllowsAccess
{
    public function __construct(
        private TenantContext $context,
        private BillingEnforcement $enforcement,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enforcement->active()) {
            return $next($request);
        }

        $tenant = $this->context->tenant();
        $subscription = $tenant === null ? null : Subscription::query()->forTenant($tenant)->first(['id', 'status']);

        if ($subscription === null || $subscription->status->allowsAccess()) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Assinatura da empresa sem acesso: '.$subscription->status->label().'.'], 402);
        }

        if ($this->context->hasRoleIn([TenantRole::Owner, TenantRole::Admin])) {
            return redirect()->route('tenant.subscription.show');
        }

        return Inertia::render('tenant/subscription/blocked', [
            'status' => $subscription->status->label(),
        ])->toResponse($request)->setStatusCode(402);
    }
}
