<?php

namespace App\Http\Controllers\Site;

use App\Enums\RequestOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreSiteServiceRequest;
use App\Models\AssistantSetting;
use App\Models\Service;
use App\Services\Assistant\AssistantOperations;
use App\Services\ServiceRequestService;
use App\Services\Websites\PublicSiteContext;
use App\Services\Websites\SegmentTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Solicitar atendimento" of a public company site: the visitor leaves name, phone and what they need, and the
 * request enters the company's triage queue with origin "Site público" (or "Portal Regional" when the visitor came
 * from the portal through ?origem=portal). The page is never indexed. It is rate limited
 * per visitor and company, has a hidden trap field for robots, and confirms only after the request is saved. The
 * visitor never sees any record of the company beyond the published site.
 */
class SiteRequestController extends Controller
{
    public function __construct(private PublicSiteContext $sites) {}

    public function show(Request $request): Response
    {
        $site = $this->sites->forRequest($request) ?? abort(404);
        $content = $site['content'];
        $services = $content['services'] ?? [];
        $chosen = $request->integer('servico');

        return response()->view('website.solicitar', [
            'content' => $content,
            'theme' => SegmentTheme::for($content['segment_slug'] ?? null),
            'services' => $services,
            'chosen' => in_array($chosen, $this->publishedServiceIds($content), true) ? $chosen : null,
            'home' => $site['on_host'] ? $site['base'].'/' : $site['base'],
            'action' => $site['base'].'/solicitar',
            'fromPortal' => $request->query('origem') === 'portal' || old('origem') === 'portal',
            'assistant' => AssistantSetting::query()->forTenant($site['tenant'])->value('enabled') ? $site['base'].'/atendimento' : null,
            'sent' => (bool) $request->session()->get('site_request_sent', false),
            'homolog' => $site['tenant']->isHomolog(),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(StoreSiteServiceRequest $request, AssistantOperations $operations, ServiceRequestService $requests): RedirectResponse
    {
        $site = $this->sites->forRequest($request) ?? abort(404);
        $fromPortal = $request->validated('origem') === 'portal';
        $back = redirect()->to($site['base'].'/solicitar'.($fromPortal ? '?origem=portal' : ''));

        // A filled trap field is a robot: it sees the same confirmation, and nothing is saved.
        if (filled($request->validated('website'))) {
            return $back->with('site_request_sent', true);
        }

        $tenant = $site['tenant'];
        $serviceId = (int) $request->validated('service_id');
        $service = ! in_array($serviceId, $this->publishedServiceIds($site['content']), true) ? null : Service::query()->forTenant($tenant)->where('active', true)->whereKey($serviceId)->first();

        $description = trim(implode("\n", array_filter([
            $service !== null ? 'Serviço de interesse: '.$service->name : null,
            (string) $request->validated('description'),
            filled($request->validated('email')) ? 'E-mail informado: '.$request->validated('email') : null,
        ])));

        // The origin is the visitor's attribution only (link from the portal); the company always comes from the address.
        $origin = $fromPortal ? RequestOrigin::Portal : RequestOrigin::PublicSite;

        DB::transaction(function () use ($tenant, $request, $operations, $requests, $service, $description, $origin): void {
            $customer = $operations->findOrCreateCustomer($tenant, trim((string) $request->validated('name')), (string) $request->phoneDigits());

            $serviceRequest = $requests->create($tenant, ['customer_id' => $customer->getKey(), 'description' => $description], null, $origin);

            if ($service !== null) {
                try {
                    $requests->triage($serviceRequest, ['service_id' => $service->getKey()], null);
                } catch (ValidationException) {
                    // The service cannot be scheduled now: the request stays in the queue for the team to triage.
                }
            }
        });

        return $back->with('site_request_sent', true);
    }

    /**
     * Ids of the services in the published version. Versions published before the service id was stored have none, so
     * a visitor choice there is simply not linked.
     *
     * @param  array<string, mixed>  $content
     * @return list<int>
     */
    private function publishedServiceIds(array $content): array
    {
        $ids = [];

        foreach ((array) ($content['services'] ?? []) as $service) {
            if (is_array($service) && is_int($service['id'] ?? null)) {
                $ids[] = $service['id'];
            }
        }

        return $ids;
    }
}
