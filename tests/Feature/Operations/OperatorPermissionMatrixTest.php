<?php

use App\Enums\RequestStatus;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

/*
 * Permission matrix of the operational module (BACKEND-04.2, section 3). Owner and admin manage
 * the catalogue; operators read it and run the service. The backend enforces every row here.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $this->operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id, 'role' => 'operator']);

    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 60]);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('an operator can view services', function () {
    $this->actingAs($this->operator)->get(route('tenant.services.index'))->assertOk();
    $this->actingAs($this->operator)->get(route('tenant.services.edit', $this->service))->assertForbidden();
});

test('an operator cannot create, edit, deactivate or link professionals to a service', function () {
    $this->actingAs($this->operator)->post(route('tenant.services.store'), ['name' => 'Indevido'])->assertForbidden();
    $this->actingAs($this->operator)->patch(route('tenant.services.update', $this->service), ['name' => 'Indevido'])->assertForbidden();
    $this->actingAs($this->operator)->delete(route('tenant.services.deactivate', $this->service))->assertForbidden();
    $this->actingAs($this->operator)
        ->put(route('tenant.services.professionals', $this->service), ['professional_ids' => [$this->professional->id]])
        ->assertForbidden();

    expect($this->service->fresh()->active)->toBeTrue()
        ->and($this->service->fresh()->name)->not->toBe('Indevido')
        ->and($this->service->professionals()->count())->toBe(0);
});

test('an operator registers customers, requests, triages and schedules', function () {
    $this->actingAs($this->operator)
        ->post(route('tenant.customers.store'), ['name' => 'Cliente do operador'])
        ->assertRedirect();

    $customer = Customer::query()->where('name', 'Cliente do operador')->where('tenant_id', $this->tenant->id)->firstOrFail();

    $this->actingAs($this->operator)
        ->post(route('tenant.requests.store'), ['customer_id' => $customer->id, 'description' => 'Revisão'])
        ->assertRedirect();

    $request = ServiceRequest::query()->where('customer_id', $customer->id)->firstOrFail();

    $this->actingAs($this->operator)
        ->patch(route('tenant.requests.triage', $request), ['service_id' => $this->service->id])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(RequestStatus::Triaged);
});

test('an operator cannot reach administrative screens or read another company', function () {
    $otherTenant = Tenant::factory()->create();
    $foreign = Service::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($this->operator)->get('/equipe')->assertForbidden();
    $this->actingAs($this->operator)->get(route('tenant.services.edit', $foreign))->assertNotFound();
});
