<?php

namespace App\Http\Controllers\Site;

use App\Enums\ConversationStatus;
use App\Enums\DomainKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\SendAssistantMessageRequest;
use App\Models\AssistantConversation;
use App\Models\AssistantSetting;
use App\Models\Tenant;
use App\Services\Assistant\AssistantConversations;
use App\Services\Assistant\AssistantFlow;
use App\Services\Websites\PublicSiteAddress;
use App\Services\Websites\WebsiteResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Visitor chat of a company site, at /{segment}/{company}/atendimento on the main domain or at /atendimento on the
 * company's own verified domain. The company comes from the address only (the two URL slugs or the hostname), never
 * from the session or the payload. The visitor is identified by an httpOnly token cookie scoped to the assistant path
 * of that address, so the conversations of different companies on the main domain never share a cookie.
 */
class AssistantController extends Controller
{
    public function __construct(private WebsiteResolver $resolver, private PublicSiteAddress $address) {}

    public function show(Request $request): RedirectResponse|View
    {
        $redirect = $this->redirectFromHost($request);

        if ($redirect !== null) {
            return $redirect;
        }

        $site = $this->siteFor($request) ?? abort(404);
        $tenant = $site['tenant'];
        $setting = AssistantSetting::query()->forTenant($tenant)->first();
        $content = $site['content'];

        return view('website.atendimento', [
            'companyName' => $setting?->display_name ?: $tenant->name,
            'available' => $this->available($setting),
            'contact' => $content['contact'] ?? [],
            'privacyPolicyUrl' => $setting?->privacy_policy_url,
            'accent' => in_array($content['accent_color'] ?? '', ['#0F172A', '#1E3A5F', '#2563EB', '#06B6D4'], true) ? $content['accent_color'] : '#2563EB',
            'siteUrl' => $site['home'],
            'endpoint' => $site['assistant_path'],
        ]);
    }

    /**
     * Start a conversation, or resume the one of this visitor. Returns the current messages.
     */
    public function start(Request $request, AssistantConversations $conversations, AssistantFlow $flow): JsonResponse
    {
        $site = $this->siteFor($request);
        $tenant = $site['tenant'] ?? null;
        $setting = $tenant === null ? null : AssistantSetting::query()->forTenant($tenant)->first();

        if ($site === null || $tenant === null || ! $this->available($setting)) {
            return response()->json(['available' => false, 'message' => 'O atendimento virtual não está disponível agora. Use os contatos da empresa.'], 503);
        }

        $started = $conversations->start($tenant, $setting, $this->visitorToken($request));

        if ($started['created']) {
            $flow->greet($tenant, $setting, $started['conversation']);
            $started['conversation']->refresh();
        }

        $response = response()->json($this->payload($conversations, $started['conversation'], 0));

        return $started['token'] === null
            ? $response
            : $response->cookie($this->cookieName(), $started['token'], (int) config('assistant.session_cookie_days') * 1440, $site['assistant_path'], null, $request->isSecure(), true, false, 'lax');
    }

    /**
     * Messages after a given id, for the polling of the page.
     */
    public function messages(Request $request, AssistantConversations $conversations): JsonResponse
    {
        $tenant = $this->siteFor($request)['tenant'] ?? null;
        $conversation = $tenant === null ? null : $conversations->findByToken($tenant, $this->visitorToken($request));

        if ($conversation === null) {
            return response()->json(['message' => 'Conversa não encontrada. Inicie o atendimento novamente.'], 404);
        }

        return response()->json($this->payload($conversations, $conversation, (int) $request->integer('after')));
    }

    /**
     * A visitor message. The flow answers, hands off or stays silent, and the page receives the new messages.
     */
    public function send(SendAssistantMessageRequest $request, AssistantConversations $conversations, AssistantFlow $flow): JsonResponse
    {
        $tenant = $this->siteFor($request)['tenant'] ?? null;
        $setting = $tenant === null ? null : AssistantSetting::query()->forTenant($tenant)->first();

        if ($tenant === null || ! $this->available($setting)) {
            return response()->json(['available' => false, 'message' => 'O atendimento virtual não está disponível agora.'], 503);
        }

        $conversation = $conversations->findByToken($tenant, $this->visitorToken($request));

        if ($conversation === null || $conversation->status === ConversationStatus::Closed) {
            return response()->json(['message' => 'Conversa não encontrada. Atualize a página para iniciar um novo atendimento.'], 409);
        }

        $body = (string) $request->validated('body');

        $conversations->receiveOnce(
            $conversation,
            $request->validated('client_id'),
            fn (AssistantConversation $locked) => $flow->receive($tenant, $setting, $locked, $body),
        );

        return response()->json($this->payload($conversations, $conversation->refresh(), (int) $request->integer('after')));
    }

    /**
     * The live site behind this request, with the addresses the page uses. On the main domain it comes from the two
     * URL slugs; on another host, only the company's main custom domain serves the assistant.
     *
     * @return array{tenant: Tenant, content: array<string, mixed>, home: string, assistant_path: string}|null
     */
    private function siteFor(Request $request): ?array
    {
        $segment = $request->route('segment');
        $company = $request->route('company');

        if (is_string($segment) && is_string($company)) {
            $site = $this->resolver->resolvePath($segment, $company);

            return $site === null ? null : [
                'tenant' => $site['tenant'],
                'content' => $site['content'],
                'home' => (string) $this->address->home($site['tenant']),
                'assistant_path' => $this->address->path($site['tenant']).'/atendimento',
            ];
        }

        $site = $this->resolver->resolve($request->getHost());

        if ($site === null || $site['domain']->kind !== DomainKind::Custom || $this->address->customHost($site['tenant']) !== $request->getHost()) {
            return null;
        }

        return [
            'tenant' => $site['tenant'],
            'content' => $site['content'],
            'home' => $this->address->hostBase($request->getHost()).'/',
            'assistant_path' => '/atendimento',
        ];
    }

    /**
     * On a company hostname, the assistant page of an earlier platform subdomain moves to the standard address, and a
     * secondary custom domain moves to the company's main one.
     */
    private function redirectFromHost(Request $request): ?RedirectResponse
    {
        if (is_string($request->route('segment'))) {
            return null;
        }

        $site = $this->resolver->resolve($request->getHost()) ?? abort(404);

        if ($site['domain']->kind === DomainKind::Subdomain) {
            return redirect()->away(($this->address->assistant($site['tenant']) ?? abort(404)), 301);
        }

        $customHost = $this->address->customHost($site['tenant']);

        if ($customHost !== null && $customHost !== $request->getHost()) {
            return redirect()->away($this->address->hostBase($customHost).'/atendimento', 301);
        }

        return null;
    }

    /**
     * The visitor's token from the cookie. Only a plain string counts: a crafted cookie sent as an array is ignored.
     */
    private function visitorToken(Request $request): ?string
    {
        $token = $request->cookie($this->cookieName());

        return is_string($token) ? $token : null;
    }

    private function available(?AssistantSetting $setting): bool
    {
        return $setting !== null && $setting->enabled;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AssistantConversations $conversations, AssistantConversation $conversation, int $after): array
    {
        return [
            'available' => true,
            'status' => $conversation->status->value,
            'awaiting_human' => $conversation->status !== ConversationStatus::Active,
            'status_label' => $conversation->status->label(),
            'messages' => $conversations->messagesAfter($conversation, $after),
        ];
    }

    private function cookieName(): string
    {
        return (string) config('assistant.session_cookie');
    }
}
