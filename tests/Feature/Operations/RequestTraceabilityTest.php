<?php

use App\Enums\AssistantMessageRole;
use App\Enums\ConversationStatus;
use App\Enums\DiscoverySource;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Assistant\AssistantConversations;
use App\Services\Assistant\AssistantOperations;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * GROWTH-03, item 7: the request detail and the customer history show the commercial origin, the entry channel and
 * who conducted each request, from the records already kept and isolated by company.
 */
beforeEach(function () {
    Notification::fake();

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create(['name' => 'Ana Equipe']);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
});

function traceRequest(Tenant $tenant, Customer $customer, RequestOrigin $origin, array $attributes = []): ServiceRequest
{
    return ServiceRequest::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'status' => RequestStatus::Requested, 'origin' => $origin, ...$attributes]);
}

function traceConversation(Tenant $tenant, ?ServiceRequest $request, ConversationStatus $status, array $attributes = []): AssistantConversation
{
    return AssistantConversation::query()->create([
        'tenant_id' => $tenant->id, 'channel' => 'site', 'session_hash' => bin2hex(random_bytes(32)), 'state' => 'human', 'status' => $status,
        'collected' => [], 'last_activity_at' => now(), 'retention_until' => now()->addDays(30), 'service_request_id' => $request?->id, ...$attributes,
    ]);
}

function traceOf(ServiceRequest $request): array
{
    $page = test()->actingAs(test()->owner)->get("/solicitacoes/{$request->id}")->assertOk();
    $props = $page->viewData('page')['props']['request'];

    return [$props['commercial_origin']['label'], $props['commercial_origin']['detail'], $props['channel']['label'], $props['attendance']['label'], $props['attendance']['detail']];
}

test('a panel request and an old record without evidence show the existing "not identified" label and the team', function () {
    $panel = traceRequest($this->tenant, $this->customer, RequestOrigin::Panel);
    $old = traceRequest($this->tenant, $this->customer, RequestOrigin::PublicSite);

    expect(traceOf($panel))->toBe(['Não identificada', null, 'Painel (registro manual)', 'Equipe', null])
        ->and(traceOf($old))->toBe(['Não identificada', null, 'Site — formulário', 'Equipe', null]);
});

test('the site form shows the verified portal or the campaign as origin, and the form as channel', function () {
    $portal = traceRequest($this->tenant, $this->customer, RequestOrigin::Portal, ['discovery' => DiscoverySource::Portal]);
    $campaign = traceRequest($this->tenant, $this->customer, RequestOrigin::PublicSite, ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'verao']);

    expect(traceOf($portal))->toBe(['Portal Regional', 'chegada confirmada pelo link do portal', 'Site — formulário', 'Equipe', null])
        ->and(traceOf($campaign))->toBe(['Campanha', 'google · cpc · verao', 'Site — formulário', 'Equipe', null]);
});

test('the assistant alone, waiting for the team, assumed by a person or answered by the team', function () {
    $alone = traceRequest($this->tenant, $this->customer, RequestOrigin::Ai, ['discovery' => DiscoverySource::Portal]);
    traceConversation($this->tenant, $alone, ConversationStatus::Closed);

    $waiting = traceRequest($this->tenant, $this->customer, RequestOrigin::Ai);
    traceConversation($this->tenant, $waiting, ConversationStatus::AwaitingHuman, ['handoff_reason' => 'Cliente pediu uma pessoa.']);

    // Given back to the assistant after a reply of the team: the hand-off reason is cleared, the reply stays.
    $answered = traceRequest($this->tenant, $this->customer, RequestOrigin::Ai, ['utm_source' => 'instagram']);
    $conversation = traceConversation($this->tenant, $answered, ConversationStatus::Active);
    AssistantMessage::query()->create(['tenant_id' => $this->tenant->id, 'assistant_conversation_id' => $conversation->id, 'role' => AssistantMessageRole::Operator->value, 'body' => 'Olá!', 'user_id' => $this->owner->id]);

    $purged = traceRequest($this->tenant, $this->customer, RequestOrigin::Ai);

    expect(traceOf($alone))->toBe(['Portal Regional', 'chegada confirmada pelo link do portal', 'Site — atendimento virtual', 'Atendimento virtual', null])
        ->and(traceOf($waiting))->toBe(['Não identificada', null, 'Site — atendimento virtual', 'Atendimento virtual, encaminhado à equipe', 'aguardando a equipe'])
        ->and(traceOf($answered))->toBe(['Campanha', 'instagram', 'Site — atendimento virtual', 'Atendimento virtual, encaminhado à equipe', null])
        ->and(traceOf($purged))->toBe(['Não identificada', null, 'Site — atendimento virtual', 'Atendimento virtual', 'conversa não disponível']);
});

test('a conversation handed to the team and identified by it shows who attended, keeping the portal origin', function () {
    $conversation = traceConversation($this->tenant, null, ConversationStatus::Active, ['discovery' => DiscoverySource::Portal]);
    app(AssistantConversations::class)->handoff($conversation, 'Cliente pediu uma pessoa.');
    app(AssistantConversations::class)->assume($conversation->fresh(), $this->owner);
    $request = app(AssistantOperations::class)->identifyConversation($this->tenant, $conversation->fresh(), 'Maria Souza', '11988887777', null, $this->owner);

    expect(traceOf($request))->toBe(['Portal Regional', 'chegada confirmada pelo link do portal', 'Site — atendimento virtual', 'Atendimento virtual, encaminhado à equipe', 'atendido por Ana Equipe']);
});

test('a conversation of another company pointing to the request is never used', function () {
    $other = Tenant::factory()->create();
    $request = traceRequest($this->tenant, $this->customer, RequestOrigin::Panel);
    traceConversation($other, $request, ConversationStatus::HumanAssumed, ['handoff_reason' => 'Outra empresa.']);

    expect(traceOf($request))->toBe(['Não identificada', null, 'Painel (registro manual)', 'Equipe', null]);
});

test('the customer history shows each request with its own origin, channel and attendance', function () {
    $portal = traceRequest($this->tenant, $this->customer, RequestOrigin::Portal, ['discovery' => DiscoverySource::Portal]);
    $ai = traceRequest($this->tenant, $this->customer, RequestOrigin::Ai, ['utm_source' => 'google', 'assigned_user_id' => $this->owner->id]);
    traceConversation($this->tenant, $ai, ConversationStatus::HumanAssumed, ['handoff_reason' => 'Orçamento.', 'handled_by_user_id' => $this->owner->id]);
    $panel = traceRequest($this->tenant, $this->customer, RequestOrigin::Panel);

    $this->actingAs($this->owner)->get("/clientes/{$this->customer->id}")->assertOk()->assertInertia(function (AssertableInertia $page) use ($portal, $ai, $panel) {
        $page->component('tenant/customers/show')->has('history.data', 3);
        $rows = collect($page->toArray()['props']['history']['data'])->keyBy('id');

        expect($rows[$portal->id]['commercial_origin']['key'])->toBe('portal')
            ->and($rows[$portal->id]['channel']['key'])->toBe('site_form')
            ->and($rows[$portal->id]['attendance']['key'])->toBe('team')
            ->and($rows[$ai->id]['commercial_origin']['key'])->toBe('campaign')
            ->and($rows[$ai->id]['channel']['key'])->toBe('site_assistant')
            ->and($rows[$ai->id]['attendance'])->toBe(['key' => 'assistant_team', 'label' => 'Atendimento virtual, encaminhado à equipe', 'detail' => 'atendido por Ana Equipe'])
            ->and($rows[$ai->id]['assignee'])->toBe('Ana Equipe')
            ->and($rows[$panel->id]['commercial_origin']['key'])->toBe('unknown')
            ->and($rows[$panel->id]['channel']['key'])->toBe('panel')
            ->and($rows[$panel->id]['assignee'])->toBeNull();
    });
});

test('another company never sees the request or the customer', function () {
    $request = traceRequest($this->tenant, $this->customer, RequestOrigin::Panel);
    $other = Tenant::factory()->create();
    $stranger = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $other->id, 'user_id' => $stranger->id]);

    $this->actingAs($stranger)->get("/solicitacoes/{$request->id}")->assertNotFound();
    $this->actingAs($stranger)->get("/clientes/{$this->customer->id}")->assertNotFound();
});
