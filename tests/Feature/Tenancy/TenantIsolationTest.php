<?php

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Tenancy\TenantContext;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->ownerA = User::factory()->create();
    $this->ownerB = User::factory()->create();

    $this->membershipA = TenantUser::factory()->owner()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->ownerA->id,
    ]);
    $this->membershipB = TenantUser::factory()->owner()->create([
        'tenant_id' => $this->tenantB->id,
        'user_id' => $this->ownerB->id,
    ]);

    TenantSetting::factory()->create(['tenant_id' => $this->tenantA->id, 'business_name' => 'Empresa A']);
    TenantSetting::factory()->create(['tenant_id' => $this->tenantB->id, 'business_name' => 'Empresa B']);
});

test('settings returned are always those of the active tenant', function () {
    $this->actingAs($this->ownerA)
        ->getJson('/api/v1/tenant/settings')
        ->assertOk()
        ->assertJsonPath('data.business_name', 'Empresa A');
});

test('a tenant_id sent by the frontend cannot switch the active tenant', function () {
    $this->actingAs($this->ownerA)
        ->getJson('/api/v1/tenant/settings?tenant_id='.$this->tenantB->id)
        ->assertOk()
        ->assertJsonPath('data.business_name', 'Empresa A');

    $this->actingAs($this->ownerA)
        ->patchJson('/api/v1/tenant/settings', [
            'tenant_id' => $this->tenantB->id,
            'business_name' => 'Invadida',
        ])
        ->assertOk();

    expect(TenantSetting::query()->where('tenant_id', $this->tenantB->id)->value('business_name'))
        ->toBe('Empresa B');
});

test('a membership of another tenant cannot be read, updated or deactivated', function () {
    $this->actingAs($this->ownerA)
        ->patchJson('/api/v1/tenant/users/'.$this->membershipB->id, ['name' => 'Alterado'])
        ->assertNotFound();

    $this->actingAs($this->ownerA)
        ->deleteJson('/api/v1/tenant/users/'.$this->membershipB->id)
        ->assertNotFound();

    expect($this->membershipB->fresh()->isActive())->toBeTrue()
        ->and($this->ownerB->fresh()->name)->not->toBe('Alterado');
});

test('creating a user ignores a tenant_id in the payload', function () {
    $this->actingAs($this->ownerA)
        ->postJson('/api/v1/tenant/users', [
            'tenant_id' => $this->tenantB->id,
            'name' => 'Novo Operador',
            'email' => 'operador@example.com',
            'password' => 'Senha-forte-123',
            'password_confirmation' => 'Senha-forte-123',
            'role' => 'operator',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.tenant.id', $this->tenantA->id);

    expect(TenantUser::query()->where('tenant_id', $this->tenantB->id)->count())->toBe(1);
});

test('switching to a tenant without membership is refused', function () {
    $this->actingAs($this->ownerA)
        ->postJson('/api/v1/auth/switch-tenant', ['tenant_id' => $this->tenantB->id])
        ->assertForbidden();
});

test('a user can switch safely between their own tenants', function () {
    $secondTenant = Tenant::factory()->create();
    TenantSetting::factory()->create(['tenant_id' => $secondTenant->id, 'business_name' => 'Segunda Empresa']);
    TenantUser::factory()->admin()->create([
        'tenant_id' => $secondTenant->id,
        'user_id' => $this->ownerA->id,
    ]);

    $this->actingAs($this->ownerA)
        ->postJson('/api/v1/auth/switch-tenant', ['tenant_id' => $secondTenant->id])
        ->assertOk()
        ->assertJsonPath('data.tenant.id', $secondTenant->id)
        ->assertJsonPath('data.role', TenantRole::Admin->value);

    $this->actingAs($this->ownerA)
        ->getJson('/api/v1/tenant/settings')
        ->assertOk();

    expect(TenantUser::query()->where('user_id', $this->ownerA->id)->count())->toBe(2);
});

test('a tenant suspended after membership is not reachable through a switch', function () {
    $suspended = Tenant::factory()->create(['status' => TenantStatus::Suspended]);
    TenantUser::factory()->owner()->create([
        'tenant_id' => $suspended->id,
        'user_id' => $this->ownerA->id,
    ]);

    $this->actingAs($this->ownerA)
        ->postJson('/api/v1/auth/switch-tenant', ['tenant_id' => $suspended->id])
        ->assertForbidden();

    $this->actingAs($this->ownerA)
        ->getJson('/api/v1/auth/tenants')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('a platform root admin without membership gets no tenant data', function () {
    $root = User::factory()->rootAdmin()->create();

    $this->actingAs($root)
        ->getJson('/api/v1/tenant/settings')
        ->assertForbidden();

    $this->actingAs($root)
        ->getJson('/api/v1/tenant/users')
        ->assertForbidden();
});

test('the tenant context is cleared after each request', function () {
    $this->actingAs($this->ownerA)
        ->getJson('/api/v1/tenant/settings')
        ->assertOk();

    expect(app(TenantContext::class)->tenant())->toBeNull();
});
