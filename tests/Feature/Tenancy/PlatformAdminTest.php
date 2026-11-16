<?php

use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->root = User::factory()->rootAdmin()->create();
    $this->tenant = Tenant::factory()->create();
});

test('a root admin can list and inspect tenants', function () {
    $this->actingAs($this->root)
        ->getJson('/api/v1/admin/tenants')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->root)
        ->getJson('/api/v1/admin/tenants/'.$this->tenant->id)
        ->assertOk()
        ->assertJsonPath('data.id', $this->tenant->id);
});

test('a root admin can suspend and reactivate a tenant, leaving an audit trail', function () {
    $this->actingAs($this->root)
        ->postJson('/api/v1/admin/tenants/'.$this->tenant->id.'/suspend')
        ->assertOk()
        ->assertJsonPath('data.status', TenantStatus::Suspended->value);

    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Suspended);

    $this->actingAs($this->root)
        ->postJson('/api/v1/admin/tenants/'.$this->tenant->id.'/activate')
        ->assertOk()
        ->assertJsonPath('data.status', TenantStatus::Active->value);

    $actions = AuditLog::query()
        ->where('tenant_id', $this->tenant->id)
        ->orderBy('id')
        ->pluck('action')
        ->all();

    expect($actions)->toBe(['tenant.suspended', 'tenant.activated'])
        ->and(AuditLog::query()->value('user_id'))->toBe($this->root->id);
});

test('a root admin can manage segments', function () {
    $this->actingAs($this->root)
        ->postJson('/api/v1/admin/segments', ['name' => 'Piscinas', 'description' => 'Manutenção de piscinas'])
        ->assertSuccessful()
        ->assertJsonPath('data.slug', 'piscinas');

    $segment = Segment::query()->where('slug', 'piscinas')->firstOrFail();

    $this->actingAs($this->root)
        ->patchJson('/api/v1/admin/segments/'.$segment->id, ['active' => false])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

test('a regular user cannot reach platform endpoints', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/admin/tenants')->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/admin/tenants/'.$this->tenant->id.'/suspend')->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/admin/segments', ['name' => 'Indevido'])->assertForbidden();

    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Active);
});

test('a guest cannot reach platform endpoints', function () {
    $this->getJson('/api/v1/admin/tenants')->assertUnauthorized();
});

test('a suspended root admin loses platform authority', function () {
    $this->root->forceFill(['status' => 'suspended'])->save();

    expect($this->root->fresh()->isRootAdmin())->toBeFalse();
});
