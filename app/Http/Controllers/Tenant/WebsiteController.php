<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateWebsiteDraftRequest;
use App\Models\Professional;
use App\Models\Service;
use App\Services\Tenancy\TenantContext;
use App\Services\Websites\WebsitePresenter;
use App\Services\Websites\WebsiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meu Site: the company's public website. Drafts are edited here; visitors see only
 * what was published. Writes require the same permission as company settings.
 */
class WebsiteController extends Controller
{
    public function index(TenantContext $context, WebsiteService $websites, WebsitePresenter $presenter): Response
    {
        $tenant = $context->requireTenant();
        $website = $websites->ensure($tenant);

        return Inertia::render('tenant/website/index', [
            'website' => $presenter->summary($tenant, $website),
            'draft' => $website->draft_content,
            'options' => [
                'services' => Service::query()->forTenant($tenant)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn (Service $service): array => ['id' => $service->id, 'name' => $service->name])->values()->all(),
                'professionals' => Professional::query()->forTenant($tenant)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn (Professional $professional): array => ['id' => $professional->id, 'name' => $professional->name])->values()->all(),
                'colors' => $presenter->colors(),
            ],
            'domains' => $presenter->domains($tenant),
            'canEdit' => Gate::allows('manageSettings', $tenant),
            'segment' => $tenant->segment?->name,
        ]);
    }

    public function saveDraft(UpdateWebsiteDraftRequest $request, TenantContext $context, WebsiteService $websites): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $changes = $this->changesFor((string) $request->validated('section'), $request->validated());

        $websites->saveDraft($tenant, $changes, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rascunho salvo. Ele só aparece no site após a publicação.']);

        return to_route('tenant.website.index');
    }

    public function publish(Request $request, TenantContext $context, WebsiteService $websites): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $version = $websites->publish($tenant, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site publicado (versão '.$version->version.').']);

        return to_route('tenant.website.index');
    }

    public function unpublish(Request $request, TenantContext $context, WebsiteService $websites): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $websites->unpublish($tenant, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site retirado do ar. Os dados foram mantidos.']);

        return to_route('tenant.website.index');
    }

    /**
     * Preview of the current draft, rendered with the public template. Never indexed.
     */
    public function preview(TenantContext $context, WebsiteService $websites): View
    {
        $tenant = $context->requireTenant();

        return view('website.site', [
            'content' => $websites->previewContent($tenant),
            'canonical' => null,
            'noindex' => true,
            'preview' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function changesFor(string $section, array $data): array
    {
        return match ($section) {
            'appearance' => ['accent_color' => $data['accent_color'], 'font' => $data['font']],
            'content' => [
                'headline' => $data['headline'] ?? null,
                'about' => $data['about'] ?? null,
                'differentials' => array_values($data['differentials'] ?? []),
                'show_hours' => (bool) ($data['show_hours'] ?? false),
            ],
            'services' => [
                'service_ids' => array_values(array_map('intval', $data['service_ids'] ?? [])),
                'show_professionals' => (bool) ($data['show_professionals'] ?? false),
                'professional_ids' => array_values(array_map('intval', $data['professional_ids'] ?? [])),
            ],
            'seo' => ['seo' => [
                'title' => $data['seo']['title'] ?? null,
                'description' => $data['seo']['description'] ?? null,
                'indexable' => (bool) ($data['seo']['indexable'] ?? true),
            ]],
            default => [],
        };
    }
}
