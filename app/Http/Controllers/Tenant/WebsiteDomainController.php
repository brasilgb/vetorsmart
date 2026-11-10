<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreWebsiteDomainRequest;
use App\Models\TenantDomain;
use App\Services\Tenancy\TenantContext;
use App\Services\Websites\WebsiteDomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WebsiteDomainController extends Controller
{
    public function store(StoreWebsiteDomainRequest $request, TenantContext $context, WebsiteDomainService $domains): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        if ($request->string('kind')->value() === 'subdomain') {
            $domains->createSubdomain($tenant, $this->actor($request));

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Endereço da plataforma criado.']);
        } else {
            $domains->addCustomDomain($tenant, (string) $request->string('hostname')->value(), $this->actor($request));

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Domínio adicionado. Publique o registro DNS indicado e depois verifique.']);
        }

        return to_route('tenant.website.index');
    }

    public function verify(Request $request, TenantContext $context, WebsiteDomainService $domains, int $domain): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $record = $this->find($tenant->getKey(), $domain);

        $verified = $domains->verify($record, $this->actor($request));

        Inertia::flash('toast', $verified
            ? ['type' => 'success', 'message' => 'Domínio verificado.']
            : ['type' => 'error', 'message' => 'Registro DNS ainda não encontrado. A propagação pode levar algum tempo.']);

        return to_route('tenant.website.index');
    }

    public function primary(Request $request, TenantContext $context, WebsiteDomainService $domains, int $domain): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $domains->makePrimary($this->find($tenant->getKey(), $domain), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Domínio principal atualizado.']);

        return to_route('tenant.website.index');
    }

    public function destroy(Request $request, TenantContext $context, WebsiteDomainService $domains, int $domain): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $domains->remove($this->find($tenant->getKey(), $domain), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Domínio removido.']);

        return to_route('tenant.website.index');
    }

    /**
     * A domain of another company is simply not found.
     */
    private function find(int $tenantId, int $domainId): TenantDomain
    {
        return TenantDomain::query()->forTenant($tenantId)->whereKey($domainId)->firstOrFail();
    }
}
