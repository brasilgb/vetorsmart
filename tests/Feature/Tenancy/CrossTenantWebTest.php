<?php

use App\Enums\AppointmentStatus;
use App\Enums\ConversationStatus;
use App\Enums\MembershipStatus;
use App\Enums\RequestStatus;
use App\Enums\TenantRole;
use App\Models\Appointment;
use App\Models\AssistantConversation;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;

/*
 * Cross-tenant access through the web (Inertia) routes and the JSON API. Every record below belongs to tenant B,
 * and the caller is a member of tenant A only. Foreign records must answer 404, never their content or an effect.
 */

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['name' => 'Empresa A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Empresa B']);

    TenantSetting::factory()->create(['tenant_id' => $this->tenantA->id, 'business_name' => 'Empresa A Ltda']);
    TenantSetting::factory()->create(['tenant_id' => $this->tenantB->id, 'business_name' => 'Empresa B Ltda']);

    $this->ownerA = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenantA->id, 'user_id' => $this->ownerA->id]);

    $this->operatorA = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenantA->id, 'user_id' => $this->operatorA->id, 'role' => TenantRole::Operator]);

    $this->ownerB = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenantB->id, 'user_id' => $this->ownerB->id]);

    // Records of tenant B.
    $this->customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cliente Secreto B']);
    $this->requestB = ServiceRequest::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'customer_id' => $this->customerB->id,
        'description' => 'Pedido confidencial de B',
    ]);
    $professionalB = Professional::factory()->create(['tenant_id' => $this->tenantB->id, 'active' => true]);
    $this->appointmentB = Appointment::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'service_request_id' => $this->requestB->id,
        'professional_id' => $professionalB->id,
        'status' => AppointmentStatus::Scheduled,
    ]);
    $this->conversationB = AssistantConversation::create([
        'tenant_id' => $this->tenantB->id,
        'channel' => 'site',
        'session_hash' => hash('sha256', 'visitante-b'),
        'state' => 'greeting',
        'status' => ConversationStatus::AwaitingHuman,
        'collected' => [],
        'invalid_attempts' => 0,
        'last_activity_at' => now(),
        'retention_until' => now()->addDays(30),
        'handoff_reason' => 'Conversa confidencial de B',
    ]);
});

test('a foreign service request cannot be read through its URL id', function () {
    $this->actingAs($this->ownerA)
        ->get('/solicitacoes/'.$this->requestB->id)
        ->assertNotFound();
});

test('a foreign service request cannot be triaged, and the record stays unchanged', function () {
    $serviceA = Service::factory()->create(['tenant_id' => $this->tenantA->id]);

    $this->actingAs($this->ownerA)
        ->patch('/solicitacoes/'.$this->requestB->id.'/triagem', ['service_id' => $serviceA->id, 'priority' => 'urgent'])
        ->assertNotFound();

    expect($this->requestB->fresh()->priority->value)->toBe('normal');
});

test('a foreign customer cannot be edited through its URL id', function () {
    $this->actingAs($this->ownerA)
        ->get('/clientes/'.$this->customerB->id.'/editar')
        ->assertNotFound();
});

test('a foreign appointment cannot be cancelled, and it stays scheduled', function () {
    $this->actingAs($this->ownerA)
        ->post('/agenda/agendamentos/'.$this->appointmentB->id.'/cancelar', ['reason' => 'teste'])
        ->assertNotFound();

    expect($this->appointmentB->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

test('a foreign assistant conversation cannot be assumed by an operator of another company', function () {
    $this->actingAs($this->ownerA)
        ->post('/atendimento-ia/conversas/'.$this->conversationB->id.'/assumir')
        ->assertNotFound();

    expect($this->conversationB->fresh()->status)->toBe(ConversationStatus::AwaitingHuman)
        ->and($this->conversationB->fresh()->handled_by_user_id)->toBeNull();
});

test('the request list of a company never shows records of another company', function () {
    ServiceRequest::factory()->create(['tenant_id' => $this->tenantA->id, 'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenantA->id])->id, 'description' => 'Pedido de A']);

    $this->actingAs($this->ownerA)
        ->get('/solicitacoes')
        ->assertOk()
        ->assertDontSee('Pedido confidencial de B')
        ->assertDontSee('Cliente Secreto B');
});

test('changing the session to another company does not expose its records', function () {
    // The membership check runs on each request: a session pointing at tenant B, where this user has no membership,
    // falls back to a membership the user really has (tenant A) or is refused. It never serves tenant B.
    $response = $this->actingAs($this->ownerA)
        ->withSession(['current_tenant_id' => $this->tenantB->id])
        ->get('/solicitacoes/'.$this->requestB->id);

    expect($response->status())->toBeIn([403, 404]);
    $response->assertDontSee('Pedido confidencial de B');
});

test('a manipulated tenant id in the URL query does not switch the company', function () {
    $this->actingAs($this->ownerA)
        ->get('/solicitacoes?tenant_id='.$this->tenantB->id)
        ->assertOk()
        ->assertDontSee('Pedido confidencial de B');
});

test('an operator without management rights gets 403 when changing assistant settings', function () {
    $this->actingAs($this->operatorA)
        ->patch('/atendimento-ia/configuracoes', ['enabled' => true, 'retention_days' => 30, 'display_name' => 'Invadido'])
        ->assertForbidden();
});

test('a user with no membership in any company is refused the operational area', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get('/solicitacoes')
        ->assertForbidden();
});

test('a suspended membership loses access to the company immediately', function () {
    TenantUser::query()
        ->where('tenant_id', $this->tenantA->id)
        ->where('user_id', $this->operatorA->id)
        ->update(['status' => MembershipStatus::Inactive]);

    $this->actingAs($this->operatorA)
        ->get('/solicitacoes')
        ->assertForbidden();
});

test('the JSON API returns 404 for a foreign customer and does not reveal its data', function () {
    $this->actingAs($this->ownerA)
        ->getJson('/api/v1/tenant/customers/'.$this->customerB->id)
        ->assertNotFound()
        ->assertDontSee('Cliente Secreto B');
});

test('the JSON API ignores a tenant id in the body when updating a customer', function () {
    $ownCustomer = Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cliente A']);

    $this->actingAs($this->ownerA)
        ->patchJson('/api/v1/tenant/customers/'.$ownCustomer->id, ['name' => 'Cliente A2', 'tenant_id' => $this->tenantB->id])
        ->assertOk();

    expect($ownCustomer->fresh()->tenant_id)->toBe($this->tenantA->id)
        ->and($ownCustomer->fresh()->name)->toBe('Cliente A2');
});

test('unauthenticated requests receive no company data', function () {
    $this->get('/solicitacoes')->assertRedirect('/login');
    $this->getJson('/api/v1/tenant/customers')->assertUnauthorized();
});

test('the foreign records stay intact after every refused attempt', function () {
    $this->actingAs($this->ownerA)->get('/solicitacoes/'.$this->requestB->id)->assertNotFound();
    $serviceA = Service::factory()->create(['tenant_id' => $this->tenantA->id]);
    $this->actingAs($this->ownerA)->patch('/solicitacoes/'.$this->requestB->id.'/triagem', ['service_id' => $serviceA->id, 'priority' => 'urgent'])->assertNotFound();

    expect(ServiceRequest::query()->whereKey($this->requestB->id)->first()->status)->toBe(RequestStatus::Requested)
        ->and(Customer::query()->whereKey($this->customerB->id)->value('name'))->toBe('Cliente Secreto B');
});
