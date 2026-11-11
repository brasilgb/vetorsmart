<?php

namespace App\Http\Controllers\Site;

use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\SendAssistantMessageRequest;
use App\Models\AssistantConversation;
use App\Models\AssistantSetting;
use App\Models\Tenant;
use App\Services\Assistant\AssistantConversations;
use App\Services\Assistant\AssistantFlow;
use App\Services\Websites\WebsiteResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Visitor chat on a company's published site. The company comes from the verified hostname only;
 * the visitor is identified by an httpOnly token cookie that is never shown to the page script.
 */
class AssistantController extends Controller
{
    public function show(Request $request, WebsiteResolver $resolver): View
    {
        $site = $resolver->resolve($request->getHost());

        if ($site === null) {
            abort(404);
        }

        $tenant = $site['domain']->tenant;
        $setting = AssistantSetting::query()->forTenant($tenant)->first();
        $content = $site['content'];

        return view('website.atendimento', [
            'companyName' => $setting?->display_name ?: $tenant->name,
            'available' => $this->available($setting),
            'contact' => $content['contact'] ?? [],
            'accent' => in_array($content['accent_color'] ?? '', ['#0F172A', '#1E3A5F', '#2563EB', '#06B6D4'], true) ? $content['accent_color'] : '#2563EB',
            'siteUrl' => config('platform.public_scheme', 'https').'://'.$site['domain']->hostname.'/',
        ]);
    }

    /**
     * Start a conversation, or resume the one of this visitor. Returns the current messages.
     */
    public function start(Request $request, WebsiteResolver $resolver, AssistantConversations $conversations, AssistantFlow $flow): JsonResponse
    {
        $tenant = $this->tenantFor($request, $resolver);
        $setting = $tenant === null ? null : AssistantSetting::query()->forTenant($tenant)->first();

        if ($tenant === null || ! $this->available($setting)) {
            return response()->json(['available' => false, 'message' => 'O atendimento virtual não está disponível agora. Use os contatos da empresa.'], 503);
        }

        $started = $conversations->start($tenant, $setting, $request->cookie($this->cookieName()));

        if ($started['created']) {
            $flow->greet($tenant, $setting, $started['conversation']);
            $started['conversation']->refresh();
        }

        $response = response()->json($this->payload($conversations, $started['conversation'], 0));

        return $started['token'] === null
            ? $response
            : $response->cookie($this->cookieName(), $started['token'], (int) config('assistant.session_cookie_days') * 1440, '/', null, $request->isSecure(), true, false, 'lax');
    }

    /**
     * Messages after a given id, for the polling of the page.
     */
    public function messages(Request $request, WebsiteResolver $resolver, AssistantConversations $conversations): JsonResponse
    {
        $conversation = $this->conversationFor($request, $resolver, $conversations);

        if ($conversation === null) {
            return response()->json(['message' => 'Conversa não encontrada. Inicie o atendimento novamente.'], 404);
        }

        return response()->json($this->payload($conversations, $conversation, (int) $request->integer('after')));
    }

    /**
     * A visitor message. The flow answers, hands off or stays silent, and the page receives the new messages.
     */
    public function send(SendAssistantMessageRequest $request, WebsiteResolver $resolver, AssistantConversations $conversations, AssistantFlow $flow): JsonResponse
    {
        $tenant = $this->tenantFor($request, $resolver);
        $setting = $tenant === null ? null : AssistantSetting::query()->forTenant($tenant)->first();

        if ($tenant === null || ! $this->available($setting)) {
            return response()->json(['available' => false, 'message' => 'O atendimento virtual não está disponível agora.'], 503);
        }

        $conversation = $conversations->findByToken($tenant, $request->cookie($this->cookieName()));

        if ($conversation === null || $conversation->status === ConversationStatus::Closed) {
            return response()->json(['message' => 'Conversa não encontrada. Atualize a página para iniciar um novo atendimento.'], 409);
        }

        $flow->receive($tenant, $setting, $conversation, (string) $request->validated('body'));

        return response()->json($this->payload($conversations, $conversation->refresh(), (int) $request->integer('after')));
    }

    private function tenantFor(Request $request, WebsiteResolver $resolver): ?Tenant
    {
        $site = $resolver->resolve($request->getHost());

        return $site === null ? null : $site['domain']->tenant;
    }

    private function conversationFor(Request $request, WebsiteResolver $resolver, AssistantConversations $conversations): ?AssistantConversation
    {
        $tenant = $this->tenantFor($request, $resolver);

        return $tenant === null ? null : $conversations->findByToken($tenant, $request->cookie($this->cookieName()));
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
