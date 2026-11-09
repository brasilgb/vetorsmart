<?php

use App\Enums\PriceType;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
});

test('a service category can be created, listed, updated and deactivated', function () {
    $created = $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/service-categories', ['name' => 'Instalação', 'sort_order' => 2])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Instalação')
        ->json('data.id');

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/service-categories')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->owner)
        ->patchJson('/api/v1/tenant/service-categories/'.$created, ['description' => 'Instalações novas'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Instalações novas');

    $this->actingAs($this->owner)
        ->deleteJson('/api/v1/tenant/service-categories/'.$created)
        ->assertOk()
        ->assertJsonPath('data.active', false);

    expect(ServiceCategory::query()->whereKey($created)->value('active'))->toBeFalse();
});

test('category names are unique within a tenant but may repeat across tenants', function () {
    ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Reparos']);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/service-categories', ['name' => 'Reparos'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $other = Tenant::factory()->create();
    ServiceCategory::factory()->create(['tenant_id' => $other->id, 'name' => 'Limpeza']);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/service-categories', ['name' => 'Limpeza'])
        ->assertSuccessful();
});

test('a service needs only a name and defaults to price on request', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'Higienização de split'])
        ->assertSuccessful()
        ->assertJsonPath('data.price_type', 'on_request')
        ->assertJsonPath('data.price', null)
        ->assertJsonPath('data.active', true);
});

test('fixed and starting_at prices require an amount, returned as a decimal string', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'Manutenção', 'price_type' => 'fixed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('price');

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'Manutenção', 'price_type' => 'fixed', 'price' => 150])
        ->assertSuccessful()
        ->assertJsonPath('data.price', '150.00');

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'Instalação', 'price_type' => 'starting_at', 'price' => '99.9'])
        ->assertSuccessful()
        ->assertJsonPath('data.price', '99.90');
});

test('an on request service cannot carry an amount', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'Consulta', 'price_type' => 'on_request', 'price' => 50])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('price');
});

test('invalid durations, prices and price types are refused', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'A', 'duration_minutes' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('duration_minutes');

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'A', 'price_type' => 'fixed', 'price' => -10])
        ->assertUnprocessable()->assertJsonValidationErrors('price');

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'A', 'price_type' => 'gratis'])
        ->assertUnprocessable()->assertJsonValidationErrors('price_type');

    expect(Service::query()->count())->toBe(0);
});

test('a service category must belong to the same tenant and be active', function () {
    $otherTenant = Tenant::factory()->create();
    $foreignCategory = ServiceCategory::factory()->create(['tenant_id' => $otherTenant->id]);
    $inactiveCategory = ServiceCategory::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'A', 'service_category_id' => $foreignCategory->id])
        ->assertUnprocessable()->assertJsonValidationErrors('service_category_id');

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/services', ['name' => 'A', 'service_category_id' => $inactiveCategory->id])
        ->assertUnprocessable()->assertJsonValidationErrors('service_category_id');
});

test('services can be filtered by category and status and sorted by price', function () {
    $climate = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Climatização']);
    $cheap = Service::factory()->fixed('50.00')->create(['tenant_id' => $this->tenant->id, 'service_category_id' => $climate->id, 'name' => 'Limpeza']);
    $expensive = Service::factory()->fixed('300.00')->create(['tenant_id' => $this->tenant->id, 'service_category_id' => $climate->id, 'name' => 'Instalação']);
    Service::factory()->inactive()->create(['tenant_id' => $this->tenant->id, 'service_category_id' => $climate->id]);
    Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sem categoria']);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/services?category_id='.$climate->id)
        ->assertJsonCount(3, 'data');

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/services?category_id='.$climate->id.'&active=1')
        ->assertJsonCount(2, 'data');

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/services?category_id='.$climate->id.'&active=1&sort=price&direction=desc')
        ->assertJsonPath('data.0.id', $expensive->id)
        ->assertJsonPath('data.1.id', $cheap->id);
});

test('an unknown sort column is refused', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/services?sort=id;DROP')
        ->assertUnprocessable();
});

test('a service can be updated and deactivated while keeping its history', function () {
    $service = Service::factory()->fixed('120.00')->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->patchJson('/api/v1/tenant/services/'.$service->id, ['price' => '130.00'])
        ->assertOk()
        ->assertJsonPath('data.price', '130.00');

    $this->actingAs($this->owner)
        ->patchJson('/api/v1/tenant/services/'.$service->id, ['price_type' => 'on_request'])
        ->assertOk()
        ->assertJsonPath('data.price', null);

    $this->actingAs($this->owner)
        ->deleteJson('/api/v1/tenant/services/'.$service->id)
        ->assertOk()
        ->assertJsonPath('data.active', false);

    $fresh = $service->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->isAvailableForScheduling())->toBeFalse()
        ->and($fresh->price_type)->toBe(PriceType::OnRequest);
});
