<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AssistantMessageRole;
use App\Enums\ConversationStatus;
use App\Enums\DomainStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Assistant\IdentifyAssistantConversationRequest;
use App\Http\Requests\Tenant\Assistant\OperatorReplyRequest;
use App\Http\Requests\Tenant\Assistant\UpdateAssistantSettingsRequest;
use App\Models\AssistantConversation;
use App\Models\AssistantSetting;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\Assistant\AssistantConversations;
use App\Services\Assistant\AssistantOperations;
use App\Services\Assistant\AssistantSettingsService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visitor assistant of the active company: its configuration (owners and admins) and the
 * conversation inbox that human attendants use (owners, admins and operators).
 */
class AssistantController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $tenant = $context->requireTenant();
        Gate::authorize('handleAssistant', $tenant);

        $setting = AssistantSetting::query()->forTenant($tenant)->first();

        $waiting = [ConversationStatus::AwaitingHuman->value, ConversationStatus::HumanAssumed->value];

        $pendingIdentification = AssistantConversation::query()
            ->forTenant($tenant)
            ->whereNull('customer_id')
            ->whereIn('status', $waiting)
            ->with(['service:id,name', 'handledBy:id,name'])
            ->orderByDesc('last_activity_at')
            ->limit(50)
            ->get()
            ->map(fn (AssistantConversation $conversation): array => $this->row($tenant, $conversation))
            ->values()
            ->all();

        $conversations = AssistantConversation::query()
            ->forTenant($tenant)
            ->where(fn ($query) => $query->whereNotNull('customer_id')->orWhereNotIn('status', $waiting))
            ->with(['customer:id,name', 'service:id,name', 'handledBy:id,name'])
            ->orderByDesc('last_activity_at')
            ->limit(50)
            ->get()
            ->map(fn (AssistantConversation $conversation): array => $this->row($tenant, $conversation))
            ->values()
            ->all();

        $services = Service::query()
            ->forTenant($tenant)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Service $service): array => ['id' => $service->getKey(), 'name' => $service->name])
            ->values()
            ->all();

        $selected = null;

        if ($request->filled('conversation')) {
            $found = AssistantConversation::query()->forTenant($tenant)->whereKey($request->integer('conversation'))->first();

            if ($found === null) {
                abort(404);
            }

            $selected = [
                ...$this->row($tenant, $found),
                'messages' => $found->messages()->get()->map(fn ($message): array => [
                    'id' => $message->getKey(),
                    'role' => $message->role,
                    'role_label' => AssistantMessageRole::from($message->role)->label(),
                    'body' => $message->body,
                    'at' => $message->created_at?->format('d/m H:i') ?? '',
                ])->values()->all(),
                'can_reply' => $found->status === ConversationStatus::HumanAssumed && $found->handled_by_user_id === $request->user()?->getKey(),
            ];
        }

        return Inertia::render('tenant/assistant/index', [
            'settings' => $this->settingsPayload($setting),
            'canManage' => Gate::allows('manageAssistant', $tenant),
            'publicUrl' => $this->publicUrl($tenant),
            'pendingIdentification' => $pendingIdentification,
            'conversations' => $conversations,
            'services' => $services,
            'selected' => $selected,
            'filters' => ['conversation' => $request->integer('conversation') ?: null],
        ]);
    }

    public function update(UpdateAssistantSettingsRequest $request, TenantContext $context, AssistantSettingsService $settings): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $context->requireTenant();
        Gate::authorize('manageAssistant', $tenant);

        $settings->update($tenant, $request->validated(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configurações da recepcionista salvas.']);

        return to_route('tenant.assistant.index');
    }

    public function assume(Request $request, TenantContext $context, AssistantConversations $conversations, AssistantConversation $conversation): RedirectResponse
    {
        $tenant = $this->authorizeHandling($context, $conversation);
        $conversations->assume($conversation, $this->actor($request));

        return to_route('tenant.assistant.index', ['conversation' => $conversation->getKey()]);
    }

    public function returnToAi(Request $request, TenantContext $context, AssistantConversations $conversations, AssistantConversation $conversation): RedirectResponse
    {
        $this->authorizeHandling($context, $conversation);
        $conversations->returnToAssistant($conversation, $this->actor($request));

        return to_route('tenant.assistant.index', ['conversation' => $conversation->getKey()]);
    }

    public function close(Request $request, TenantContext $context, AssistantConversations $conversations, AssistantConversation $conversation): RedirectResponse
    {
        $this->authorizeHandling($context, $conversation);
        $conversations->close($conversation, $this->actor($request));

        return to_route('tenant.assistant.index', ['conversation' => $conversation->getKey()]);
    }

    /**
     * Identify the visitor (name and phone) and send the conversation to the triage queue as one request, keeping its messages.
     */
    public function identify(IdentifyAssistantConversationRequest $request, TenantContext $context, AssistantOperations $operations, AssistantConversation $conversation): RedirectResponse
    {
        $tenant = $this->authorizeHandling($context, $conversation);
        $service = $request->filled('service_id')
            ? Service::query()->forTenant($tenant)->whereKey($request->integer('service_id'))->firstOrFail()
            : null;

        $serviceRequest = $operations->identifyConversation(
            $tenant,
            $conversation,
            (string) $request->validated('name'),
            (string) $request->validated('phone'),
            $service,
            $this->actor($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente identificado. Solicitação #'.$serviceRequest->getKey().' enviada à triagem.']);

        return to_route('tenant.assistant.index', ['conversation' => $conversation->getKey()]);
    }

    public function reply(OperatorReplyRequest $request, TenantContext $context, AssistantConversations $conversations, AssistantConversation $conversation): RedirectResponse
    {
        $this->authorizeHandling($context, $conversation);
        $conversations->operatorReply($conversation, $this->actor($request), $request->validated('body'));

        return to_route('tenant.assistant.index', ['conversation' => $conversation->getKey()]);
    }

    /**
     * The conversation must belong to the active company; anything else is a 404.
     */
    private function authorizeHandling(TenantContext $context, AssistantConversation $conversation): Tenant
    {
        $tenant = $context->requireTenant();
        Gate::authorize('handleAssistant', $tenant);

        abort_unless((int) $conversation->tenant_id === (int) $tenant->getKey(), 404);

        return $tenant;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Tenant $tenant, AssistantConversation $conversation): array
    {
        return [
            'id' => $conversation->getKey(),
            'customer' => $conversation->customer?->name,
            'service' => $conversation->service?->name,
            'status' => $conversation->status->value,
            'status_label' => $conversation->status->label(),
            'state_label' => $conversation->state->label(),
            'handled_by' => $conversation->handledBy?->name,
            'handled_by_me' => $conversation->handled_by_user_id !== null && $conversation->handled_by_user_id === request()->user()?->getKey(),
            'handoff_reason' => $conversation->handoff_reason,
            'identified' => $conversation->customer_id !== null,
            'request_id' => $conversation->service_request_id,
            'summary' => $conversation->status === ConversationStatus::Active ? null : ($conversation->collected['summary'] ?? null),
            'last_activity' => $conversation->last_activity_at === null ? '' : $tenant->toLocal($conversation->last_activity_at)->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(?AssistantSetting $setting): array
    {
        return [
            'enabled' => (bool) ($setting?->enabled ?? false),
            'display_name' => $setting?->display_name ?? '',
            'greeting' => $setting?->greeting ?? '',
            'presentation' => $setting?->presentation ?? '',
            'service_region' => $setting?->service_region ?? '',
            'business_hours_text' => $setting?->business_hours_text ?? '',
            'commercial_info' => $setting?->commercial_info ?? '',
            'guidance' => $setting?->guidance ?? '',
            'handoff_conditions' => $setting?->handoff_conditions ?? '',
            'retention_days' => $setting?->retention_days ?? (int) config('assistant.default_retention_days'),
        ];
    }

    /**
     * Address of the visitor page: the company's primary verified domain, or null when there is none yet.
     */
    private function publicUrl(Tenant $tenant): ?string
    {
        $domain = TenantDomain::query()
            ->forTenant($tenant)
            ->where('is_primary', true)
            ->where('status', DomainStatus::Verified)
            ->first();

        return $domain === null ? null : config('platform.public_scheme', 'https').'://'.$domain->hostname.'/atendimento';
    }
}
