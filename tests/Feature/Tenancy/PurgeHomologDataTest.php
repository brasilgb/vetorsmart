<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->homolog = Tenant::factory()->homolog()->create(['name' => '[HOMOLOG] Empresa Teste']);
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

/**
 * A company that has been through the whole operation: request with history, appointment, agenda block and an
 * assistant conversation, the rows that used to make the purge fail on the foreign keys.
 *
 * @return array<string, int>
 */
function operatedCompany(Tenant $tenant): array
{
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $request = ServiceRequest::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'service_id' => $service->id]);
    $appointment = Appointment::factory()->create(['tenant_id' => $tenant->id, 'service_request_id' => $request->id, 'professional_id' => $professional->id]);

    DB::table('service_request_events')->insert(['tenant_id' => $tenant->id, 'service_request_id' => $request->id, 'type' => 'created']);
    DB::table('professional_blocks')->insert(['tenant_id' => $tenant->id, 'professional_id' => $professional->id, 'starts_at' => now(), 'ends_at' => now()->addHour()]);
    DB::table('assistant_settings')->insert(['tenant_id' => $tenant->id, 'enabled' => true]);
    DB::table('assistant_usage')->insert(['tenant_id' => $tenant->id, 'usage_date' => now()->toDateString(), 'messages' => 3]);
    $conversation = DB::table('assistant_conversations')->insertGetId([
        'tenant_id' => $tenant->id,
        'session_hash' => str_repeat('a', 64),
        'customer_id' => $customer->id,
        'service_request_id' => $request->id,
        'appointment_id' => $appointment->id,
    ]);
    DB::table('assistant_messages')->insert(['tenant_id' => $tenant->id, 'assistant_conversation_id' => $conversation, 'role' => 'visitor', 'body' => 'Olá']);
    DB::table('assistant_operations')->insert(['tenant_id' => $tenant->id, 'assistant_conversation_id' => $conversation, 'operation' => 'schedule', 'idempotency_key' => str_repeat('b', 64)]);

    return ['request' => $request->id, 'appointment' => $appointment->id, 'conversation' => $conversation];
}

/**
 * @return array<string, int>
 */
function operationalRowsOf(int $tenantId): array
{
    return collect(['service_requests', 'service_request_events', 'appointments', 'professional_blocks', 'assistant_settings', 'assistant_usage', 'assistant_conversations', 'assistant_messages', 'assistant_operations', 'customers', 'services', 'professionals'])
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->where('tenant_id', $tenantId)->count()])
        ->all();
}

test('a homologation company that went through the whole operation is removed completely', function () {
    operatedCompany($this->homolog);
    operatedCompany($this->real);
    $realBefore = operationalRowsOf($this->real->id);

    $this->artisan('homolog:purge', ['--execute' => true])
        ->expectsQuestion('Digite exatamente "EXCLUIR HOMOLOG" para confirmar', 'EXCLUIR HOMOLOG')
        ->assertSuccessful();

    expect(Tenant::query()->whereKey($this->homolog->id)->exists())->toBeFalse()
        ->and(array_sum(operationalRowsOf($this->homolog->id)))->toBe(0)
        ->and(operationalRowsOf($this->real->id))->toBe($realBefore);
});

test('the preview counts the operational rows that will be removed', function () {
    operatedCompany($this->homolog);

    $this->artisan('homolog:purge')
        ->expectsOutputToContain('service_requests')
        ->expectsOutputToContain('assistant_conversations')
        ->assertSuccessful();

    expect(ServiceRequest::query()->where('tenant_id', $this->homolog->id)->count())->toBe(1);
});

test('a company named with the prefix but without the internal mark is not removed', function () {
    $named = Tenant::factory()->create(['name' => '[HOMOLOG] Nome sem marca']);
    operatedCompany($named);

    $this->artisan('homolog:purge', ['--execute' => true])
        ->expectsQuestion('Digite exatamente "EXCLUIR HOMOLOG" para confirmar', 'EXCLUIR HOMOLOG')
        ->assertSuccessful();

    expect(Tenant::query()->whereKey($named->id)->exists())->toBeTrue()
        ->and(ServiceRequest::query()->where('tenant_id', $named->id)->count())->toBe(1);
});

test('a platform administrator with a homologation e-mail is never removed', function () {
    $root = User::factory()->rootAdmin()->create(['email' => 'homolog.root@example.test']);

    $this->artisan('homolog:purge', ['--execute' => true])
        ->expectsQuestion('Digite exatamente "EXCLUIR HOMOLOG" para confirmar', 'EXCLUIR HOMOLOG')
        ->assertSuccessful();

    expect(User::query()->whereKey($root->id)->exists())->toBeTrue();
});
