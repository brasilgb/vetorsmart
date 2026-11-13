<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Factories\SegmentFactory;

test('registration records the creation of the company and its owner', function () {
    $segment = SegmentFactory::new()->create();

    $this->post(route('register.store'), [
        'company_name' => 'Auditada Ltda',
        'segment_id' => $segment->id,
        'name' => 'Dono',
        'email' => 'dono@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $tenant = Tenant::query()->where('name', 'Auditada Ltda')->firstOrFail();

    expect(AuditLog::query()->where('tenant_id', $tenant->id)->pluck('action')->all())
        ->toContain('tenant.created', 'tenant.owner_registered');
});

test('settings changes record which fields changed, not their values', function () {
    $tenant = Tenant::factory()->create();
    TenantSetting::factory()->create(['tenant_id' => $tenant->id, 'business_name' => 'Antiga']);
    $owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    $this->actingAs($owner)
        ->patchJson('/api/v1/tenant/settings', ['business_name' => 'Nova', 'city' => 'Curitiba'])
        ->assertOk();

    $log = AuditLog::query()->where('action', 'tenant_settings.updated')->firstOrFail();

    expect($log->tenant_id)->toBe($tenant->id)
        ->and($log->user_id)->toBe($owner->id)
        ->and($log->metadata['fields'])->toEqualCanonicalizing(['business_name', 'city']);
});

test('user creation and role changes are audited with the responsible user', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    $this->actingAs($owner)
        ->postJson('/api/v1/tenant/users', [
            'name' => 'Novo',
            'email' => 'novo@example.com',
            'password' => 'Senha-forte-123',
            'password_confirmation' => 'Senha-forte-123',
            'role' => 'operator',
        ])
        ->assertSuccessful();

    $membership = TenantUser::query()->where('role', 'operator')->firstOrFail();

    $this->actingAs($owner)
        ->patchJson('/api/v1/tenant/users/'.$membership->id, ['role' => 'admin'])
        ->assertOk();

    $actions = AuditLog::query()->where('tenant_id', $tenant->id)->pluck('action')->all();

    expect($actions)->toContain('tenant_user.created', 'tenant_user.role_changed')
        ->and(AuditLog::query()->where('action', 'tenant_user.role_changed')->value('metadata'))
        ->toMatchArray(['from' => 'operator', 'to' => 'admin']);
});

test('sensitive values are never stored in audit metadata', function () {
    $logged = app(AuditLogger::class)->record('test.sensitive', null, null, [
        'password' => 'segredo-123',
        'api_token' => 'token-xyz',
        'nested' => ['remember_token' => 'abc', 'visible' => 'ok'],
        'fields' => ['name'],
    ]);

    $stored = AuditLog::query()->findOrFail($logged->id)->metadata;

    // MySQL JSON columns do not preserve key order, so compare the content regardless of order.
    expect($stored)->toEqualCanonicalizing(['nested' => ['visible' => 'ok'], 'fields' => ['name']])
        ->and(json_encode($stored))->not->toContain('segredo-123')
        ->and(json_encode($stored))->not->toContain('token-xyz');
});
