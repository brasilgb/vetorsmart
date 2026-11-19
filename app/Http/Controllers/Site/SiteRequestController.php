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
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            // One token per form shown: a second send of the same form (double click, reload, slow network) is recognised.
            'sendToken' => is_string(old('envio')) && preg_match('/^[A-Za-z0-9]{40}$/', old('envio')) === 1 ? old('envio') : Str::random(40),
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

        $register = fn (): int => DB::transaction(function () use ($tenant, $request, $operations, $requests, $service, $description, $origin): int {
            $customer = $operations->findOrCreateCustomer($tenant, trim((string) $request->validated('name')), (string) $request->phoneDigits());

            $serviceRequest = $requests->create($tenant, ['customer_id' => $customer->getKey(), 'description' => $description], null, $origin);

            if ($service !== null) {
                try {
                    $requests->triage($serviceRequest, ['service_id' => $service->getKey()], null);
                } catch (ValidationException) {
                    // The service cannot be scheduled now: the request stays in the queue for the team to triage.
                }
            }

            return (int) $serviceRequest->getKey();
        });

        $token = $request->validated('envio');

        if (! is_string($token)) {
            $register();

            return $back->with('site_request_sent', true);
        }

        // The same form sent again creates nothing new. The token is marked as used only after the request is saved, so
        // a failed attempt can be sent again and never yields a confirmation without a record.
        $key = 'site-request:'.$tenant->getKey().':'.hash('sha256', $token);
        $lock = Cache::lock($key.':lock', 15);

        try {
            $lock->block((int) config('platform.site_request_lock_wait', 10));
        } catch (LockTimeoutException) {
            return $back->withInput($request->except('website'))->withErrors(['envio' => 'Sua solicitação ainda está sendo registrada. Aguarde alguns segundos e tente novamente.']);
        }

        try {
            if (! Cache::has($key)) {
                Cache::put($key, $register(), now()->addHours(2));
            }
        } finally {
            $lock->release();
        }

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
