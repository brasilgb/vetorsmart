<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateWebsiteSubdomainRequest;
use App\Services\Tenancy\TenantContext;
use App\Services\Websites\TenantSubdomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Changes the company's platform subdomain. Only who manages the company's settings may do it; the company is always
 * the one bound to the session, never one named in the request.
 */
class WebsiteSubdomainController extends Controller
{
    public function update(UpdateWebsiteSubdomainRequest $request, TenantContext $context, TenantSubdomainService $subdomains): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $domain = $subdomains->change($tenant, (string) $request->string('subdomain')->value(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Endereço atualizado para '.$domain->hostname.'. O endereço anterior redireciona para o novo.']);

        return to_route('tenant.website.index');
    }
}
