<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreWebsiteImageRequest;
use App\Http\Requests\Tenant\UpdateWebsiteDraftRequest;
use App\Models\AssistantSetting;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Location\MunicipalityDirectory;
use App\Services\Portal\PortalDirectory;
use App\Services\Tenancy\TenantContext;
use App\Services\Websites\SegmentTheme;
use App\Services\Websites\SeoSuggestion;
use App\Services\Websites\WebsiteDraft;
use App\Services\Websites\WebsiteMedia;
use App\Services\Websites\WebsitePresenter;
use App\Services\Websites\WebsiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Meu Site: the company's public website. Drafts are edited here; visitors see only
 * what was published. Writes require the same permission as company settings.
 */
class WebsiteController extends Controller
{
    public function index(TenantContext $context, WebsiteService $websites, WebsitePresenter $presenter, PortalDirectory $portal, MunicipalityDirectory $municipalities, SeoSuggestion $seo): Response
    {
        $tenant = $context->requireTenant();
        $tenant->loadMissing('segment');
        $website = $websites->ensure($tenant);
        $draft = [...WebsiteDraft::defaults(), ...($website->draft_content ?? [])];
        $setting = TenantSetting::query()->forTenant($tenant)->first();
        $catalog = Service::query()->forTenant($tenant)->where('active', true)->orderBy('sort_order')->orderBy('name')->get();
        $settingsBase = $municipalities->byName($setting?->city, $setting?->state);
        $base = $municipalities->find($draft['base_municipality_id']) ?? $settingsBase;
        $selected = collect(array_map('intval', (array) $draft['service_ids']))->map(fn (int $id): ?Service => $catalog->firstWhere('id', $id))->filter()->values();
        $businessName = $setting === null ? $tenant->name : $setting->business_name;

        return Inertia::render('tenant/website/index', [
            'website' => $presenter->summary($tenant, $website),
            'draft' => $draft,
            'options' => [
                'services' => $catalog->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $this->catalogPrice($service),
                ])->values()->all(),
                'professionals' => Professional::query()->forTenant($tenant)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn (Professional $professional): array => ['id' => $professional->id, 'name' => $professional->name])->values()->all(),
                'colors' => $presenter->colors(),
                'palettes' => SegmentTheme::palettes($tenant->segment?->slug),
            ],
            'business' => [
                'name' => $businessName,
                'contacts' => [
                    'phone' => $tenant->phone,
                    'whatsapp' => $setting?->whatsapp,
                    'email' => $tenant->email,
                    'address' => $setting?->address,
                    'hours' => is_array($setting?->business_hours) && collect($setting->business_hours)->filter()->isNotEmpty(),
                ],
                'settings_city' => $settingsBase?->toPublic(),
            ],
            'location' => [
                'base' => $base?->toPublic(),
                'served' => array_map(fn ($municipality): array => $municipality->toPublic(), $municipalities->many(array_map('intval', (array) $draft['served_municipality_ids']))),
            ],
            'seoSuggestion' => $seo->suggest((string) $businessName, $tenant->segment?->name, array_values(array_map(fn (Service $service): string => $service->name, $selected->all())), $base?->name, $base?->state, count((array) $draft['served_municipality_ids'])),
            'domains' => $presenter->domains($tenant),
            'canEdit' => Gate::allows('manageSettings', $tenant),
            'segment' => $tenant->segment?->name,
            'serviceRegion' => AssistantSetting::query()->forTenant($tenant)->value('service_region'),
            'portal' => [
                'listed' => (bool) $website->portal_listed,
                'issues' => $portal->issues($tenant, $website),
                'url' => config('platform.public_scheme', 'https').'://'.config('platform.portal_host').'/',
            ],
        ]);
    }

    public function saveDraft(UpdateWebsiteDraftRequest $request, TenantContext $context, WebsiteService $websites): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $current = [...WebsiteDraft::defaults(), ...($websites->ensure($tenant)->draft_content ?? [])];
        $changes = $this->changesFor((string) $request->validated('section'), $request->validated(), $current, $tenant);

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

    /**
     * Consent to appear in the Regional Portal. Takes effect at once and shows only what is already published.
     */
    public function portal(Request $request, TenantContext $context, WebsiteService $websites): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $listed = $request->validate(['listed' => ['required', 'boolean']])['listed'];
        $websites->setPortalListing($tenant, (bool) $listed, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => $listed ? 'Sua empresa participa do Portal Regional.' : 'Sua empresa saiu do Portal Regional.']);

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
     * Preview of the current draft, rendered with the public template. Never indexed. With ?dispositivo=celular the
     * same preview is shown inside a phone-sized frame.
     */
    public function preview(Request $request, TenantContext $context, WebsiteService $websites): View
    {
        $tenant = $context->requireTenant();

        if ($request->query('dispositivo') === 'celular') {
            return view('website.preview-device', ['frame' => url('/meu-site/previa')]);
        }

        $content = $websites->previewContent($tenant);

        return view('website.site', [
            'content' => $content,
            'theme' => SegmentTheme::for($content['segment_slug'] ?? null),
            'canonical' => null,
            'noindex' => true,
            'preview' => true,
            'mediaBase' => url('/meu-site/previa/midia').'/',
            'ogImage' => null,
            'links' => ['home' => null, 'services' => null, 'assistant' => null, 'request' => '#'],
        ]);
    }

    /**
     * An image of the draft, for the preview only. The file must be in the company's own folder and referenced by its
     * draft; images of another company never resolve here.
     */
    public function previewMedia(string $file, TenantContext $context, WebsiteService $websites, WebsiteMedia $media): HttpResponse
    {
        $tenant = $context->requireTenant();
        $website = $websites->ensure($tenant);
        $path = $media->path($tenant, $file);

        if (! $media->isReferenced($website->draft_content ?? [], $file) || ! Storage::disk(WebsiteMedia::DISK)->exists($path)) {
            abort(404);
        }

        return Storage::disk(WebsiteMedia::DISK)->response($path, $file, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeImage(StoreWebsiteImageRequest $request, string $kind, TenantContext $context, WebsiteService $websites): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $upload = $request->file('image');
        abort_unless($upload instanceof UploadedFile, 422);

        $websites->storeImage($tenant, $kind, $upload, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->imageLabel($kind).' salva no rascunho. Publique para exibir no site.']);

        return to_route('tenant.website.index');
    }

    public function removeImage(Request $request, string $kind, TenantContext $context, WebsiteService $websites): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);

        $websites->removeImage($tenant, $kind, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->imageLabel($kind).' removida do rascunho. Publique para atualizar o site.']);

        return to_route('tenant.website.index');
    }

    /**
     * Photo of one published service. The service must be an active service of the company of the session.
     */
    public function storeServicePhoto(StoreWebsiteImageRequest $request, int $service, TenantContext $context, WebsiteService $websites): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);
        $model = Service::query()->forTenant($tenant)->whereKey($service)->firstOrFail();

        $upload = $request->file('image');
        abort_unless($upload instanceof UploadedFile, 422);

        $websites->setServicePhoto($tenant, (int) $model->getKey(), $upload, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto do serviço salva no rascunho. Publique para exibir no site.']);

        return to_route('tenant.website.index');
    }

    public function removeServicePhoto(Request $request, int $service, TenantContext $context, WebsiteService $websites): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageSettings', $tenant);
        $model = Service::query()->forTenant($tenant)->whereKey($service)->firstOrFail();

        $websites->setServicePhoto($tenant, (int) $model->getKey(), null, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto do serviço removida do rascunho. Publique para atualizar o site.']);

        return to_route('tenant.website.index');
    }

    private function imageLabel(string $kind): string
    {
        return match ($kind) {
            'logo' => 'Logomarca',
            'about' => 'Foto de "Quem somos"',
            default => 'Imagem principal',
        };
    }

    private function catalogPrice(Service $service): string
    {
        return match ($service->price_type->value) {
            'fixed' => $service->price === null ? 'Sob consulta' : 'R$ '.number_format((float) $service->price, 2, ',', '.'),
            'starting_at' => $service->price === null ? 'Sob consulta' : 'A partir de R$ '.number_format((float) $service->price, 2, ',', '.'),
            default => 'Sob consulta',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $current  the draft as it is now
     * @return array<string, mixed>
     */
    private function changesFor(string $section, array $data, array $current, Tenant $tenant): array
    {
        return match ($section) {
            'identity' => ['headline' => $data['headline'] ?? null],
            'photo' => ['hero_alt' => $data['hero_alt'] ?? null],
            'about' => [
                'about' => $data['about'] ?? null,
                'differentials' => array_values(array_filter($data['differentials'] ?? [], fn ($item): bool => is_string($item) && trim($item) !== '')),
                'trust_items' => array_values(array_filter(array_map(fn ($item): array => [
                    'label' => trim((string) ($item['label'] ?? '')),
                    'source' => trim((string) ($item['source'] ?? '')),
                    'url' => is_string($item['url'] ?? null) && $item['url'] !== '' ? $item['url'] : null,
                ], $data['trust_items'] ?? []), fn (array $item): bool => $item['label'] !== '')),
            ],
            'location' => [
                'base_municipality_id' => (int) $data['base_municipality_id'],
                'served_municipality_ids' => array_values(array_diff(array_map('intval', $data['served_municipality_ids'] ?? []), [(int) $data['base_municipality_id']])),
            ],
            'mode' => ['service_mode' => $data['service_mode']],
            'services' => [
                // Only services of this company; the order is the one chosen in the editor.
                'service_ids' => array_values(array_intersect(
                    array_map('intval', $data['service_ids'] ?? []),
                    Service::query()->forTenant($tenant)->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                )),
                'service_details' => $this->serviceDetails($data['service_details'] ?? null, $current['service_details'] ?? []),
                'show_professionals' => (bool) ($data['show_professionals'] ?? false),
                'show_prices' => (bool) ($data['show_prices'] ?? true),
                'professional_ids' => array_values(array_map('intval', $data['professional_ids'] ?? [])),
            ],
            'contact' => [
                'show_phone' => (bool) ($data['show_phone'] ?? false),
                'show_whatsapp' => (bool) ($data['show_whatsapp'] ?? false),
                'show_email' => (bool) ($data['show_email'] ?? false),
                'show_address' => (bool) ($data['show_address'] ?? false),
                'show_hours' => (bool) ($data['show_hours'] ?? false),
            ],
            'colors' => [
                'palette' => $data['palette'] ?? null,
                'accent_color' => strtoupper((string) $data['accent_color']),
                'font' => $data['font'],
            ],
            'appearance' => ['accent_color' => strtoupper((string) $data['accent_color']), 'font' => $data['font']],
            'content' => [
                'headline' => $data['headline'] ?? null,
                'about' => $data['about'] ?? null,
                'differentials' => array_values($data['differentials'] ?? []),
                'show_hours' => (bool) ($data['show_hours'] ?? false),
            ],
            'seo' => ['seo' => [
                'title' => $data['seo']['title'] ?? null,
                'description' => $data['seo']['description'] ?? null,
                'indexable' => (bool) ($data['seo']['indexable'] ?? true),
            ]],
            default => [],
        };
    }

    /**
     * Site description and public price of each service, keeping the photos already sent (they are changed by their own
     * upload, never by this form). Keyed by service id (PHP turns the numeric keys into integers).
     *
     * @return array<array-key, array<string, mixed>>
     */
    private function serviceDetails(mixed $submitted, mixed $current): array
    {
        $details = [];

        foreach (is_array($current) ? $current : [] as $id => $detail) {
            if (is_array($detail)) {
                $details[(string) $id] = $detail;
            }
        }

        if (! is_array($submitted)) {
            return $details;
        }

        foreach ($submitted as $id => $detail) {
            if (! is_array($detail) || ! ctype_digit((string) $id)) {
                continue;
            }

            $details[(string) $id] = [
                ...($details[(string) $id] ?? []),
                'description' => is_string($detail['description'] ?? null) && trim($detail['description']) !== '' ? trim($detail['description']) : null,
                'price_public' => (bool) ($detail['price_public'] ?? true),
            ];
        }

        return $details;
    }
}
