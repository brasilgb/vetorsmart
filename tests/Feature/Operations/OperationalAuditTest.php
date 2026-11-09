<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('customer creation, update and deactivation are audited with the responsible user', function () {
    $id = $this->actingAs($this->owner)->postJson('/api/v1/tenant/customers', ['name' => 'Cliente'])->json('data.id');
    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/customers/'.$id, ['city' => 'Recife'])->assertOk();
    $this->actingAs($this->owner)->deleteJson('/api/v1/tenant/customers/'.$id)->assertOk();

    $actions = AuditLog::query()
        ->where('auditable_type', (new Customer)->getMorphClass())
        ->where('auditable_id', $id)
        ->orderBy('id')
        ->pluck('action')
        ->all();

    expect($actions)->toBe(['customer.created', 'customer.updated', 'customer.deactivated'])
        ->and(AuditLog::query()->where('action', 'customer.created')->value('user_id'))->toBe($this->owner->id)
        ->and(AuditLog::query()->where('action', 'customer.updated')->value('metadata'))->toMatchArray(['fields' => ['city']]);
});

test('price changes are audited by field name, never by value', function () {
    $id = $this->actingAs($this->owner)->postJson('/api/v1/tenant/services', ['name' => 'Revisão', 'price_type' => 'fixed', 'price' => 100])->json('data.id');
    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/services/'.$id, ['price' => 120])->assertOk();

    $metadata = AuditLog::query()->where('action', 'service.updated')->value('metadata');

    expect($metadata['fields'])->toContain('price')
        ->and(json_encode($metadata))->not->toContain('120');
});

test('professional service links record which services were added and removed', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $first = Service::factory()->create(['tenant_id' => $this->tenant->id]);
    $second = Service::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)->putJson('/api/v1/tenant/professionals/'.$professional->id.'/services', ['service_ids' => [$first->id]])->assertOk();
    $this->actingAs($this->owner)->putJson('/api/v1/tenant/professionals/'.$professional->id.'/services', ['service_ids' => [$second->id]])->assertOk();

    $logs = AuditLog::query()->where('action', 'professional.services_synced')->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->metadata['added'])->toBe([$first->id])
        ->and($logs[1]->metadata['added'])->toBe([$second->id])
        ->and($logs[1]->metadata['removed'])->toBe([$first->id]);
});

test('professional deactivation and activation are audited', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)->deleteJson('/api/v1/tenant/professionals/'.$professional->id)->assertOk();
    $this->actingAs($this->owner)->postJson('/api/v1/tenant/professionals/'.$professional->id.'/activate')->assertOk();

    expect(AuditLog::query()->where('auditable_id', $professional->id)->orderBy('id')->pluck('action')->all())
        ->toBe(['professional.deactivated', 'professional.activated']);
});

test('settings changes are audited with field names only', function () {
    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', [
        'welcome_message' => 'Olá! Segredo-não-logar',
        'phone' => '11999990000',
    ])->assertOk();

    $log = AuditLog::query()->where('action', 'tenant_settings.updated')->firstOrFail();

    expect($log->metadata['fields'])->toEqualCanonicalizing(['welcome_message', 'phone'])
        ->and(json_encode($log->metadata))->not->toContain('Segredo-não-logar');
});

test('a no-op update does not create an audit entry', function () {
    $id = $this->actingAs($this->owner)->postJson('/api/v1/tenant/customers', ['name' => 'Sem mudança'])->json('data.id');
    $before = AuditLog::query()->count();

    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/customers/'.$id, ['name' => 'Sem mudança'])->assertOk();

    expect(AuditLog::query()->count())->toBe($before);
});
