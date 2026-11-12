<?php

use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->ownerA = User::factory()->create();
    $this->ownerB = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenantA->id, 'user_id' => $this->ownerA->id]);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenantB->id, 'user_id' => $this->ownerB->id]);

    $this->customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cliente B']);
    $this->categoryB = ServiceCategory::factory()->create(['tenant_id' => $this->tenantB->id]);
    $this->serviceB = Service::factory()->create(['tenant_id' => $this->tenantB->id, 'service_category_id' => $this->categoryB->id]);
    $this->professionalB = Professional::factory()->create(['tenant_id' => $this->tenantB->id]);
});

test('lists never return records of another tenant', function () {
    Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
    ServiceCategory::factory()->create(['tenant_id' => $this->tenantA->id]);
    Service::factory()->create(['tenant_id' => $this->tenantA->id]);
    Professional::factory()->create(['tenant_id' => $this->tenantA->id]);

    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/customers')
        ->assertJsonCount(1, 'data')->assertJsonMissing(['name' => 'Cliente B']);

    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/service-categories')
        ->assertJsonCount(1, 'data')->assertJsonMissing(['id' => $this->categoryB->id]);

    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/services')
        ->assertJsonCount(1, 'data')->assertJsonMissing(['id' => $this->serviceB->id]);

    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/professionals')
        ->assertJsonCount(1, 'data')->assertJsonMissing(['id' => $this->professionalB->id]);
});

test('reading, updating or deactivating another tenant record returns 404', function () {
    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/customers/'.$this->customerB->id)->assertNotFound();
    $this->actingAs($this->ownerA)->patchJson('/api/v1/tenant/customers/'.$this->customerB->id, ['name' => 'Invadido'])->assertNotFound();
    $this->actingAs($this->ownerA)->deleteJson('/api/v1/tenant/customers/'.$this->customerB->id)->assertNotFound();

    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/service-categories/'.$this->categoryB->id)->assertNotFound();
    $this->actingAs($this->ownerA)->patchJson('/api/v1/tenant/service-categories/'.$this->categoryB->id, ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->ownerA)->deleteJson('/api/v1/tenant/service-categories/'.$this->categoryB->id)->assertNotFound();

    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/services/'.$this->serviceB->id)->assertNotFound();
    $this->actingAs($this->ownerA)->patchJson('/api/v1/tenant/services/'.$this->serviceB->id, ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->ownerA)->deleteJson('/api/v1/tenant/services/'.$this->serviceB->id)->assertNotFound();

    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/professionals/'.$this->professionalB->id)->assertNotFound();
    $this->actingAs($this->ownerA)->patchJson('/api/v1/tenant/professionals/'.$this->professionalB->id, ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->ownerA)->postJson('/api/v1/tenant/professionals/'.$this->professionalB->id.'/activate')->assertNotFound();
    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/professionals/'.$this->professionalB->id.'/services')->assertNotFound();
    $this->actingAs($this->ownerA)->putJson('/api/v1/tenant/professionals/'.$this->professionalB->id.'/services', ['service_ids' => []])->assertNotFound();

    expect(Customer::query()->whereKey($this->customerB->id)->value('name'))->toBe('Cliente B')
        ->and(Customer::query()->whereKey($this->customerB->id)->value('status')->value)->toBe('active');
});

test('a tenant_id sent in the payload is ignored on every create', function () {
    $this->actingAs($this->ownerA)->postJson('/api/v1/tenant/customers', ['name' => 'Novo', 'tenant_id' => $this->tenantB->id])
        ->assertSuccessful()->assertJsonMissingPath('data.tenant_id');

    $this->actingAs($this->ownerA)->postJson('/api/v1/tenant/service-categories', ['name' => 'Nova', 'tenant_id' => $this->tenantB->id])
        ->assertSuccessful();

    $this->actingAs($this->ownerA)->postJson('/api/v1/tenant/services', ['name' => 'Novo', 'tenant_id' => $this->tenantB->id])
        ->assertSuccessful();

    $this->actingAs($this->ownerA)->postJson('/api/v1/tenant/professionals', ['name' => 'Novo', 'tenant_id' => $this->tenantB->id])
        ->assertSuccessful();

    expect(Customer::query()->where('name', 'Novo')->value('tenant_id'))->toBe($this->tenantA->id)
        ->and(ServiceCategory::query()->where('name', 'Nova')->value('tenant_id'))->toBe($this->tenantA->id)
        ->and(Service::query()->where('name', 'Novo')->value('tenant_id'))->toBe($this->tenantA->id)
        ->and(Professional::query()->where('name', 'Novo')->value('tenant_id'))->toBe($this->tenantA->id);
});

test('a service cannot be created with a category of another tenant', function () {
    $this->actingAs($this->ownerA)
        ->postJson('/api/v1/tenant/services', ['name' => 'Indevido', 'service_category_id' => $this->categoryB->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_category_id');

    $this->actingAs($this->ownerA)
        ->patchJson('/api/v1/tenant/services/'.Service::factory()->create(['tenant_id' => $this->tenantA->id])->id, [
            'service_category_id' => $this->categoryB->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_category_id');
});

test('a professional cannot be linked to a service of another tenant', function () {
    $own = Professional::factory()->create(['tenant_id' => $this->tenantA->id]);

    $this->actingAs($this->ownerA)
        ->putJson('/api/v1/tenant/professionals/'.$own->id.'/services', ['service_ids' => [$this->serviceB->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_ids');

    expect($own->services()->count())->toBe(0);
});

test('switching to a tenant without membership does not reveal its data', function () {
    $this->actingAs($this->ownerA)
        ->postJson('/api/v1/auth/switch-tenant', ['tenant_id' => $this->tenantB->id])
        ->assertForbidden();
});

test('the platform root admin cannot read operational data of a tenant', function () {
    $root = User::factory()->rootAdmin()->withConfirmedTwoFactor()->create();

    $this->actingAs($root)->getJson('/api/v1/tenant/customers')->assertForbidden();
    $this->actingAs($root)->getJson('/api/v1/tenant/services')->assertForbidden();
    $this->actingAs($root)->getJson('/api/v1/tenant/professionals')->assertForbidden();
    $this->actingAs($root)->getJson('/api/v1/tenant/service-categories')->assertForbidden();
});

test('the operational tenant context is cleared after each request', function () {
    $this->actingAs($this->ownerA)->getJson('/api/v1/tenant/customers')->assertOk();

    expect(app(TenantContext::class)->tenant())->toBeNull();
});
