<?php

use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
});

test('a professional needs only a name and does not need a login', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/professionals', ['name' => 'João Eletricista'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'João Eletricista')
        ->assertJsonPath('data.active', true);

    expect(User::query()->where('name', 'João Eletricista')->exists())->toBeFalse();
});

test('a professional can be updated, deactivated and reactivated', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->patchJson('/api/v1/tenant/professionals/'.$professional->id, ['phone' => '(11) 99999-0000'])
        ->assertOk()
        ->assertJsonPath('data.phone', '11999990000');

    $this->actingAs($this->owner)
        ->deleteJson('/api/v1/tenant/professionals/'.$professional->id)
        ->assertOk()
        ->assertJsonPath('data.active', false);

    expect($professional->fresh()->isAvailableForScheduling())->toBeFalse();

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/professionals/'.$professional->id.'/activate')
        ->assertOk()
        ->assertJsonPath('data.active', true);
});

test('services can be linked to a professional, replacing the previous set', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $install = Service::factory()->fixed()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação']);
    $clean = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Higienização']);
    $repair = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Reparo']);

    $this->actingAs($this->owner)
        ->putJson('/api/v1/tenant/professionals/'.$professional->id.'/services', ['service_ids' => [$install->id, $clean->id]])
        ->assertOk();

    expect($professional->services()->pluck('services.id')->sort()->values()->all())
        ->toBe([$install->id, $clean->id]);

    $this->actingAs($this->owner)
        ->putJson('/api/v1/tenant/professionals/'.$professional->id.'/services', ['service_ids' => [$repair->id]])
        ->assertOk();

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/professionals/'.$professional->id.'/services')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Reparo');
});

test('an empty list removes every link', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
    $professional->services()->attach($service->id, ['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->putJson('/api/v1/tenant/professionals/'.$professional->id.'/services', ['service_ids' => []])
        ->assertOk();

    expect($professional->services()->count())->toBe(0);
});

test('a service of another tenant cannot be linked', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $other = Tenant::factory()->create();
    $foreign = Service::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->owner)
        ->putJson('/api/v1/tenant/professionals/'.$professional->id.'/services', ['service_ids' => [$foreign->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_ids');

    expect($professional->services()->count())->toBe(0);
});

test('an inactive service cannot receive new links, but existing links are kept', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
    $professional->services()->attach($service->id, ['tenant_id' => $this->tenant->id]);

    $service->forceFill(['active' => false])->save();

    $this->actingAs($this->owner)
        ->putJson('/api/v1/tenant/professionals/'.$professional->id.'/services', ['service_ids' => [$service->id]])
        ->assertOk();

    expect($professional->services()->count())->toBe(1);

    $newService = Service::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->putJson('/api/v1/tenant/professionals/'.$professional->id.'/services', ['service_ids' => [$service->id, $newService->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_ids');
});

test('a service id that does not exist in the tenant is refused', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->putJson('/api/v1/tenant/professionals/'.$professional->id.'/services', ['service_ids' => [999999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_ids');
});

test('professionals can be searched by name and phone digits', function () {
    Professional::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Carla Encanadora', 'phone' => '11988887777']);
    Professional::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Diego Técnico', 'phone' => '21977776666']);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/professionals?q=carla')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Carla Encanadora');

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/professionals?q=988887777')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Carla Encanadora');
});
