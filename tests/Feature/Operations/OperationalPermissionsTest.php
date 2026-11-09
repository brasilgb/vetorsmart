<?php

use App\Enums\TenantStatus;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->operator = User::factory()->create();

    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    TenantUser::factory()->admin()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id]);
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id, 'role' => 'operator']);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->category = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('an operator registers and updates customers but cannot deactivate them', function () {
    $this->actingAs($this->operator)->postJson('/api/v1/tenant/customers', ['name' => 'Atendimento rápido'])->assertSuccessful();
    $this->actingAs($this->operator)->patchJson('/api/v1/tenant/customers/'.$this->customer->id, ['notes' => 'Prefere manhã'])->assertOk();
    $this->actingAs($this->operator)->deleteJson('/api/v1/tenant/customers/'.$this->customer->id)->assertForbidden();
});

test('an operator can read the catalog but cannot change it', function () {
    $this->actingAs($this->operator)->getJson('/api/v1/tenant/services')->assertOk();
    $this->actingAs($this->operator)->getJson('/api/v1/tenant/service-categories')->assertOk();
    $this->actingAs($this->operator)->getJson('/api/v1/tenant/professionals')->assertOk();

    $this->actingAs($this->operator)->postJson('/api/v1/tenant/services', ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->operator)->patchJson('/api/v1/tenant/services/'.$this->service->id, ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->operator)->deleteJson('/api/v1/tenant/services/'.$this->service->id)->assertForbidden();
    $this->actingAs($this->operator)->postJson('/api/v1/tenant/service-categories', ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->operator)->postJson('/api/v1/tenant/professionals', ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->operator)->putJson('/api/v1/tenant/professionals/'.$this->professional->id.'/services', ['service_ids' => []])->assertForbidden();
});

test('an admin manages the catalog, professionals and customer status', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/tenant/services', ['name' => 'Novo serviço'])->assertSuccessful();
    $this->actingAs($this->admin)->postJson('/api/v1/tenant/service-categories', ['name' => 'Nova categoria'])->assertSuccessful();
    $this->actingAs($this->admin)->postJson('/api/v1/tenant/professionals', ['name' => 'Novo profissional'])->assertSuccessful();
    $this->actingAs($this->admin)->putJson('/api/v1/tenant/professionals/'.$this->professional->id.'/services', ['service_ids' => [$this->service->id]])->assertOk();
    $this->actingAs($this->admin)->deleteJson('/api/v1/tenant/customers/'.$this->customer->id)->assertOk();
    $this->actingAs($this->admin)->deleteJson('/api/v1/tenant/services/'.$this->service->id)->assertOk();
});

test('an owner has full administrative control of the tenant', function () {
    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/services/'.$this->service->id, ['price_type' => 'on_request'])->assertOk();
    $this->actingAs($this->owner)->deleteJson('/api/v1/tenant/professionals/'.$this->professional->id)->assertOk();
    $this->actingAs($this->owner)->postJson('/api/v1/tenant/professionals/'.$this->professional->id.'/activate')->assertOk();
});

test('every operational endpoint is blocked when the tenant is suspended', function () {
    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    $this->actingAs($this->owner)->getJson('/api/v1/tenant/customers')->assertForbidden();
    $this->actingAs($this->owner)->postJson('/api/v1/tenant/customers', ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->owner)->getJson('/api/v1/tenant/services')->assertForbidden();
    $this->actingAs($this->owner)->getJson('/api/v1/tenant/professionals')->assertForbidden();
    $this->actingAs($this->owner)->getJson('/api/v1/tenant/service-categories')->assertForbidden();
});

test('a suspended user loses access to operational endpoints on the next request', function () {
    $this->operator->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($this->operator)->getJson('/api/v1/tenant/customers')->assertUnauthorized();
});

test('a guest cannot reach any operational endpoint', function () {
    $this->getJson('/api/v1/tenant/customers')->assertUnauthorized();
    $this->getJson('/api/v1/tenant/services')->assertUnauthorized();
    $this->getJson('/api/v1/tenant/professionals')->assertUnauthorized();
});
