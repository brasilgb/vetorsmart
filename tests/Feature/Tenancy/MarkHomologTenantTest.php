<?php

use App\Models\AuditLog;
use App\Models\Tenant;

test('the operator marks a company as homologation after confirming it', function () {
    $tenant = Tenant::factory()->create(['name' => 'Empresa de Teste 02']);

    $this->artisan('homolog:mark', ['tenant' => $tenant->id])
        ->expectsConfirmation('Marcar esta empresa como homologação?', 'yes')
        ->assertSuccessful();

    expect($tenant->fresh()->isHomolog())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'platform.tenant_marked_homolog')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

test('declining the confirmation leaves the company unmarked', function () {
    $tenant = Tenant::factory()->create();

    $this->artisan('homolog:mark', ['tenant' => $tenant->id])
        ->expectsConfirmation('Marcar esta empresa como homologação?', 'no')
        ->assertFailed();

    expect($tenant->fresh()->isHomolog())->toBeFalse();
});

test('marking is refused without an interactive terminal', function () {
    $tenant = Tenant::factory()->create();

    $this->artisan('homolog:mark', ['tenant' => $tenant->id, '--no-interaction' => true])->assertFailed();

    expect($tenant->fresh()->isHomolog())->toBeFalse();
});

test('an unknown company is reported and nothing changes', function () {
    $this->artisan('homolog:mark', ['tenant' => 999999])->assertFailed();
});

test('the internal mark cannot be set through mass assignment', function () {
    $tenant = Tenant::factory()->create();

    $tenant->fill(['is_homolog' => true])->save();

    expect($tenant->fresh()->isHomolog())->toBeFalse();
});
