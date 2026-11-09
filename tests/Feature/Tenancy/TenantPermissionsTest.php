<?php

use App\Enums\MembershipStatus;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id, 'business_name' => 'Empresa']);

    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->operator = User::factory()->create();

    $this->ownerMembership = TenantUser::factory()->owner()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
    ]);
    $this->adminMembership = TenantUser::factory()->admin()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
    ]);
    $this->operatorMembership = TenantUser::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->operator->id,
        'role' => TenantRole::Operator,
    ]);
});

test('an operator can read settings but cannot change them', function () {
    $this->actingAs($this->operator)->getJson('/api/v1/tenant/settings')->assertOk();

    $this->actingAs($this->operator)
        ->patchJson('/api/v1/tenant/settings', ['business_name' => 'Nova'])
        ->assertForbidden();
});

test('an operator cannot list or create users', function () {
    $this->actingAs($this->operator)->getJson('/api/v1/tenant/users')->assertForbidden();

    $this->actingAs($this->operator)
        ->postJson('/api/v1/tenant/users', [
            'name' => 'Terceiro',
            'email' => 'terceiro@example.com',
            'password' => 'Senha-forte-123',
            'password_confirmation' => 'Senha-forte-123',
            'role' => 'operator',
        ])
        ->assertForbidden();
});

test('an admin can update settings and create operators', function () {
    $this->actingAs($this->admin)
        ->patchJson('/api/v1/tenant/settings', ['business_name' => 'Alterada'])
        ->assertOk()
        ->assertJsonPath('data.business_name', 'Alterada');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/tenant/users', [
            'name' => 'Operador',
            'email' => 'operador@example.com',
            'password' => 'Senha-forte-123',
            'password_confirmation' => 'Senha-forte-123',
            'role' => 'operator',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.role', 'operator');
});

test('an admin cannot grant owner or admin roles', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/tenant/users', [
            'name' => 'Promovido',
            'email' => 'promovido@example.com',
            'password' => 'Senha-forte-123',
            'password_confirmation' => 'Senha-forte-123',
            'role' => 'owner',
        ])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->patchJson('/api/v1/tenant/users/'.$this->operatorMembership->id, ['role' => 'admin'])
        ->assertForbidden();
});

test('an admin cannot manage owners or admins', function () {
    $this->actingAs($this->admin)
        ->patchJson('/api/v1/tenant/users/'.$this->ownerMembership->id, ['name' => 'Alterado'])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/tenant/users/'.$this->ownerMembership->id)
        ->assertForbidden();
});

test('an owner can promote an operator to admin and manage every user', function () {
    $this->actingAs($this->owner)
        ->patchJson('/api/v1/tenant/users/'.$this->operatorMembership->id, ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('data.role', 'admin');

    expect($this->operatorMembership->fresh()->role)->toBe(TenantRole::Admin);

    $this->actingAs($this->owner)
        ->deleteJson('/api/v1/tenant/users/'.$this->adminMembership->id)
        ->assertOk()
        ->assertJsonPath('data.status', MembershipStatus::Inactive->value);

    expect($this->adminMembership->fresh()->isActive())->toBeFalse();
});

test('the last active owner cannot be demoted or deactivated', function () {
    $this->actingAs($this->owner)
        ->patchJson('/api/v1/tenant/users/'.$this->ownerMembership->id, ['role' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->actingAs($this->owner)
        ->deleteJson('/api/v1/tenant/users/'.$this->ownerMembership->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($this->ownerMembership->fresh()->role)->toBe(TenantRole::Owner)
        ->and($this->ownerMembership->fresh()->isActive())->toBeTrue();
});

test('the same user cannot be linked twice to one tenant', function () {
    expect(fn () => TenantUser::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
    ]))->toThrow(QueryException::class);
});

test('a user may belong to more than one tenant', function () {
    $otherTenant = Tenant::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $this->owner->id,
    ]);

    expect(TenantUser::query()->where('user_id', $this->owner->id)->count())->toBe(2);
});

test('a tenant has only one settings record', function () {
    expect(fn () => TenantSetting::factory()->create(['tenant_id' => $this->tenant->id]))
        ->toThrow(QueryException::class);
});
