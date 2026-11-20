<?php

use App\Enums\DiscoverySource;
use App\Enums\RequestOrigin;
use App\Models\AssistantConversation;
use App\Models\AssistantSetting;
use App\Models\Customer;
use App\Models\Segment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Assistant\AssistantOperations;
use App\Services\Portal\PortalReferral;
use App\Services\ServiceRequestService;
use App\Services\Websites\WebsiteService;
use Illuminate\Support\Facades\Notification;

/**
 * JOURNEY-01: the Regional Portal attribution is signed by the portal and kept on the server by the site, so the
 * browser cannot claim it; it reaches the request form and the assistant, and survives the hand-off to a human.
 */
beforeEach(function () {
    Notification::fake();
    $segment = Segment::factory()->create(['name' => 'Climatização', 'slug' => 'climatizacao', 'active' => true]);

    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment_id' => $segment->id]);
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id, 'business_name' => 'Clima Total', 'city' => 'Campinas', 'state' => 'SP']);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    AssistantSetting::create(['tenant_id' => $this->tenant->id, 'enabled' => true, 'display_name' => 'Clima Total', 'daily_message_limit' => 100, 'daily_token_limit' => 1000, 'retention_days' => 30]);
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação de split']);

    $websites = app(WebsiteService::class);
    $websites->saveDraft($this->tenant, ['service_ids' => [$service->id]], $this->owner);
    $websites->publish($this->tenant, $this->owner);

    $this->other = Tenant::factory()->create(['segment_id' => $segment->id]);
    $this->referral = app(PortalReferral::class);
});

function sendFromSite(): void
{
    test()->post('/climatizacao/clima-total/solicitar', ['name' => 'Maria Souza', 'phone' => '19988887777', 'description' => 'Instalar um split.', 'consent' => '1'])
        ->assertSessionHas('site_request_sent', true);
}

test('a signed portal link attributes the request to the portal, as origin and as discovery', function () {
    $this->get('/climatizacao/clima-total?'.$this->referral->query($this->tenant->id))->assertOk();
    sendFromSite();

    expect(ServiceRequest::query()->sole()->only(['origin', 'discovery']))->toBe(['origin' => RequestOrigin::Portal, 'discovery' => DiscoverySource::Portal]);
});

test('a link signed for another company, an expired one or a tampered one counts as a direct visit', function (string $case) {
    $ref = match ($case) {
        'other company' => $this->referral->token($this->other->id, now()->getTimestamp()),
        'expired' => $this->referral->token($this->tenant->id, now()->subDays(PortalReferral::LINK_DAYS + 1)->getTimestamp()),
        'tampered' => substr_replace($this->referral->token($this->tenant->id, now()->getTimestamp()), '0000', -4),
        'unsigned' => '',
    };

    $this->get('/climatizacao/clima-total?origem=portal'.($ref === '' ? '' : '&ref='.$ref))->assertOk();
    sendFromSite();

    expect(ServiceRequest::query()->sole()->only(['origin', 'discovery']))->toBe(['origin' => RequestOrigin::PublicSite, 'discovery' => null]);
})->with(['other company', 'expired', 'tampered', 'unsigned']);

test('the arrival attributes the requests of that visit only for a limited time', function () {
    $this->get('/climatizacao/clima-total?'.$this->referral->query($this->tenant->id));
    $this->travel(PortalReferral::VISIT_HOURS + 1)->hours();
    sendFromSite();

    expect(ServiceRequest::query()->sole()->origin)->toBe(RequestOrigin::PublicSite);
});

test('an assistant conversation started after a portal arrival keeps the portal discovery up to the request, also after the hand-off to a human', function () {
    $this->get('/climatizacao/clima-total/atendimento?'.$this->referral->query($this->tenant->id))->assertOk();
    $this->postJson('/climatizacao/clima-total/atendimento/conversa')->assertOk();

    $conversation = AssistantConversation::query()->sole();
    expect($conversation->discovery)->toBe(DiscoverySource::Portal);

    // The team identifies the visitor after the hand-off: the channel is the assistant, the discovery the portal.
    $request = app(AssistantOperations::class)->identifyConversation($this->tenant, $conversation, 'Maria Souza', '19988887777', null, $this->owner);

    expect($request->only(['origin', 'discovery']))->toBe(['origin' => RequestOrigin::Ai, 'discovery' => DiscoverySource::Portal]);
});

test('a conversation of a direct visit has no discovery', function () {
    $this->postJson('/climatizacao/clima-total/atendimento/conversa')->assertOk();

    expect(AssistantConversation::query()->sole()->discovery)->toBeNull();
});

test('the panel never sets a discovery, and the portal origin always implies it', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $requests = app(ServiceRequestService::class);

    $panel = $requests->create($this->tenant, ['customer_id' => $customer->id, 'description' => 'Pedido.', 'discovery' => 'portal'], $this->owner);
    $portal = $requests->create($this->tenant, ['customer_id' => $customer->id, 'description' => 'Pedido.'], null, RequestOrigin::Portal);

    expect($panel->discovery)->toBeNull()->and($portal->discovery)->toBe(DiscoverySource::Portal);
});
