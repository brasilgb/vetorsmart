<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

beforeEach(function () {
    $this->homolog = Tenant::factory()->create(['name' => '[HOMOLOG] Empresa Teste']);
    $this->homologUser = User::factory()->create(['email' => 'homolog.dono@example.test']);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->homolog->id, 'user_id' => $this->homologUser->id]);
    Customer::factory()->create(['tenant_id' => $this->homolog->id]);

    $this->real = Tenant::factory()->create(['name' => 'Empresa Real Ltda']);
    $this->realUser = User::factory()->create(['email' => 'dona@cliente.com.br']);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->real->id, 'user_id' => $this->realUser->id]);
    Customer::factory()->create(['tenant_id' => $this->real->id, 'name' => 'Cliente legítimo']);
});

test('the default run only previews and changes nothing', function () {
    $this->artisan('homolog:purge')->assertSuccessful();

    expect(Tenant::query()->where('name', '[HOMOLOG] Empresa Teste')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'homolog.dono@example.test')->exists())->toBeTrue();
});

test('removal is refused without a terminal, even with the flag', function () {
    $this->artisan('homolog:purge', ['--execute' => true, '--no-interaction' => true])->assertFailed();

    expect(Tenant::query()->where('name', '[HOMOLOG] Empresa Teste')->exists())->toBeTrue();
});

test('a wrong confirmation phrase removes nothing', function () {
    $this->artisan('homolog:purge', ['--execute' => true])
        ->expectsQuestion('Digite exatamente "EXCLUIR HOMOLOG" para confirmar', 'sim')
        ->assertFailed();

    expect(Tenant::query()->where('name', '[HOMOLOG] Empresa Teste')->exists())->toBeTrue();
});

test('the confirmed run removes only homologation records and keeps legitimate data', function () {
    $this->artisan('homolog:purge', ['--execute' => true])
        ->expectsQuestion('Digite exatamente "EXCLUIR HOMOLOG" para confirmar', 'EXCLUIR HOMOLOG')
        ->assertSuccessful();

    expect(Tenant::query()->where('name', '[HOMOLOG] Empresa Teste')->exists())->toBeFalse()
        ->and(Customer::query()->where('tenant_id', $this->homolog->id)->exists())->toBeFalse()
        ->and(User::query()->where('email', 'homolog.dono@example.test')->exists())->toBeFalse()
        ->and(Tenant::query()->whereKey($this->real->id)->exists())->toBeTrue()
        ->and(Customer::query()->where('name', 'Cliente legítimo')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'dona@cliente.com.br')->exists())->toBeTrue();
});

test('a homologation e-mail that still belongs to a real company is not removed', function () {
    TenantUser::factory()->admin()->create(['tenant_id' => $this->real->id, 'user_id' => $this->homologUser->id]);

    $this->artisan('homolog:purge', ['--execute' => true])
        ->expectsQuestion('Digite exatamente "EXCLUIR HOMOLOG" para confirmar', 'EXCLUIR HOMOLOG')
        ->assertSuccessful();

    expect(User::query()->where('email', 'homolog.dono@example.test')->exists())->toBeTrue()
        ->and(TenantUser::query()->where('user_id', $this->homologUser->id)->where('tenant_id', $this->real->id)->exists())->toBeTrue();
});

test('removal leaves an audit entry without tenant reference and keeps prior history', function () {
    $this->artisan('homolog:purge', ['--execute' => true])
        ->expectsQuestion('Digite exatamente "EXCLUIR HOMOLOG" para confirmar', 'EXCLUIR HOMOLOG')
        ->assertSuccessful();

    expect(AuditLog::query()->where('action', 'platform.homolog_purged')->exists())->toBeTrue();
});
