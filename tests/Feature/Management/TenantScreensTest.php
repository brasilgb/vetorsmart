<?php

use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['name' => 'Alfa']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Beta']);
    TenantSetting::factory()->create(['tenant_id' => $this->tenantA->id, 'business_name' => 'Alfa']);
    TenantSetting::factory()->create(['tenant_id' => $this->tenantB->id, 'business_name' => 'Beta']);

    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->operator = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenantA->id, 'user_id' => $this->owner->id]);
    TenantUser::factory()->admin()->create(['tenant_id' => $this->tenantA->id, 'user_id' => $this->admin->id]);
    TenantUser::factory()->create(['tenant_id' => $this->tenantA->id, 'user_id' => $this->operator->id, 'role' => 'operator']);

    $this->ownerB = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenantB->id, 'user_id' => $this->ownerB->id]);

    $this->customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cliente Beta']);
    $this->serviceB = Service::factory()->create(['tenant_id' => $this->tenantB->id]);
    $this->professionalB = Professional::factory()->create(['tenant_id' => $this->tenantB->id]);
    $this->membershipB = TenantUser::query()->where('user_id', $this->ownerB->id)->firstOrFail();
});

test('the customers screen lists only the active company and paginates', function () {
    Customer::factory()->count(16)->create(['tenant_id' => $this->tenantA->id]);

    $this->actingAs($this->owner)
        ->get('/clientes')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/customers/index')
            ->has('customers.data', 15)
            ->where('customers.total', 16));
});

test('a customer of another company cannot be opened, edited or archived through the screens', function () {
    $this->actingAs($this->owner)->get("/clientes/{$this->customerB->id}/editar")->assertNotFound();
    $this->actingAs($this->owner)->patch("/clientes/{$this->customerB->id}", ['name' => 'Invadido'])->assertNotFound();
    $this->actingAs($this->owner)->delete("/clientes/{$this->customerB->id}")->assertNotFound();

    expect($this->customerB->fresh()->name)->toBe('Cliente Beta')
        ->and($this->customerB->fresh()->status->value)->toBe('active');
});

test('a service or professional of another company is not reachable through the screens', function () {
    $this->actingAs($this->owner)->get("/servicos/{$this->serviceB->id}/editar")->assertNotFound();
    $this->actingAs($this->owner)->delete("/servicos/{$this->serviceB->id}")->assertNotFound();
    $this->actingAs($this->owner)->get("/profissionais/{$this->professionalB->id}/editar")->assertNotFound();
    $this->actingAs($this->owner)->post("/profissionais/{$this->professionalB->id}/ativar")->assertNotFound();
});

test('a membership of another company cannot be changed through the team screen', function () {
    $this->actingAs($this->owner)->patch("/equipe/{$this->membershipB->id}", ['name' => 'Invadido'])->assertNotFound();
    $this->actingAs($this->owner)->delete("/equipe/{$this->membershipB->id}")->assertNotFound();

    expect($this->membershipB->fresh()->isActive())->toBeTrue();
});

test('an operator can register customers but cannot manage services, professionals or the team', function () {
    $this->actingAs($this->operator)->post('/clientes', ['name' => 'Atendimento'])->assertRedirect('/clientes');
    expect(Customer::query()->where('name', 'Atendimento')->where('tenant_id', $this->tenantA->id)->exists())->toBeTrue();

    $this->actingAs($this->operator)->post('/servicos', ['name' => 'Indevido'])->assertForbidden();
    $this->actingAs($this->operator)->post('/profissionais', ['name' => 'Indevido'])->assertForbidden();
    $this->actingAs($this->operator)->get('/equipe')->assertForbidden();
});

test('an admin manages services and professionals but cannot grant owner access', function () {
    $this->actingAs($this->admin)->post('/servicos', ['name' => 'Revisão'])->assertRedirect('/servicos');
    expect(Service::query()->where('name', 'Revisão')->exists())->toBeTrue();

    $this->actingAs($this->admin)->post('/equipe', [
        'name' => 'Novo dono',
        'email' => 'dono2@example.com',
        'password' => 'Senha-Forte-2026',
        'password_confirmation' => 'Senha-Forte-2026',
        'role' => 'owner',
    ])->assertForbidden();
});

test('a service is archived, never deleted, and keeps its history', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenantA->id]);

    $this->actingAs($this->owner)->delete("/servicos/{$service->id}")->assertRedirect('/servicos');

    expect(Service::query()->whereKey($service->id)->exists())->toBeTrue()
        ->and($service->fresh()->active)->toBeFalse();
});

test('the last active owner cannot be suspended from the team screen', function () {
    $ownerMembership = TenantUser::query()->where('user_id', $this->owner->id)->firstOrFail();

    $this->actingAs($this->owner)->delete("/equipe/{$ownerMembership->id}")->assertSessionHasErrors('role');

    expect($ownerMembership->fresh()->isActive())->toBeTrue();
});

test('a suspended membership is reactivated by an owner and the action is audited', function () {
    $operatorMembership = TenantUser::query()->where('user_id', $this->operator->id)->firstOrFail();

    $this->actingAs($this->owner)->delete("/equipe/{$operatorMembership->id}")->assertRedirect('/equipe');
    expect($operatorMembership->fresh()->status)->toBe(MembershipStatus::Inactive);

    $this->actingAs($this->owner)->post("/equipe/{$operatorMembership->id}/ativar")->assertRedirect('/equipe');
    expect($operatorMembership->fresh()->status)->toBe(MembershipStatus::Active);

    expect(AuditLog::query()->where('action', 'tenant_user.activated')->where('tenant_id', $this->tenantA->id)->exists())->toBeTrue();
});

test('the team screen never exposes passwords or hides which member is the current user', function () {
    $this->actingAs($this->owner)
        ->get('/equipe')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/team/index')
            ->has('memberships.data', 3)
            ->missing('memberships.data.0.password')
            ->where('memberships.data.0.is_self', true));
});

test('availability windows are replaced, validated and kept inside the company', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenantA->id]);

    $this->actingAs($this->owner)
        ->putJson("/api/v1/tenant/professionals/{$professional->id}/availability", [
            'windows' => [
                ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '12:00'],
                ['weekday' => 1, 'start_time' => '13:00', 'end_time' => '17:00'],
            ],
        ])
        ->assertOk();

    expect(ProfessionalAvailability::query()->where('professional_id', $professional->id)->count())->toBe(2);

    $this->actingAs($this->owner)
        ->putJson("/api/v1/tenant/professionals/{$professional->id}/availability", [
            'windows' => [
                ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '12:00'],
                ['weekday' => 1, 'start_time' => '11:00', 'end_time' => '13:00'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('availability');

    expect(ProfessionalAvailability::query()->where('professional_id', $professional->id)->count())->toBe(2);
});

test('an availability window must end after it starts and use a valid weekday', function () {
    $professional = Professional::factory()->create(['tenant_id' => $this->tenantA->id]);

    $this->actingAs($this->owner)
        ->putJson("/api/v1/tenant/professionals/{$professional->id}/availability", [
            'windows' => [['weekday' => 9, 'start_time' => '10:00', 'end_time' => '09:00']],
        ])
        ->assertUnprocessable();
});

test('a professional of another company cannot receive availability', function () {
    $this->actingAs($this->owner)
        ->putJson("/api/v1/tenant/professionals/{$this->professionalB->id}/availability", ['windows' => []])
        ->assertNotFound();
});

test('opening hours accept valid breaks and refuse breaks outside the window', function () {
    $valid = [
        '1' => ['open' => '08:00', 'close' => '18:00', 'breaks' => [['start' => '12:00', 'end' => '13:00']]],
        '0' => null,
    ];

    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', ['business_hours' => $valid])->assertOk();

    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', [
        'business_hours' => ['1' => ['open' => '08:00', 'close' => '18:00', 'breaks' => [['start' => '17:30', 'end' => '19:00']]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('business_hours');
});

test('the brand color must be one of the approved palette tokens', function () {
    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', ['brand_color' => '#FF0000'])
        ->assertUnprocessable()->assertJsonValidationErrors('brand_color');

    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', ['brand_color' => '#06B6D4'])->assertOk();
});

test('currency and date format are restricted to the supported options', function () {
    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', ['settings' => ['currency' => 'USD']])
        ->assertUnprocessable()->assertJsonValidationErrors('settings.currency');

    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', ['settings' => ['date_format' => 'mm/dd']])
        ->assertUnprocessable()->assertJsonValidationErrors('settings.date_format');
});

test('an operator sees the settings screen read-only', function () {
    $this->actingAs($this->operator)
        ->get('/configuracoes')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/settings/index')
            ->where('canEdit', false));

    $this->actingAs($this->operator)->patch('/configuracoes', ['business_name' => 'Nova'])->assertForbidden();
});

test('the company owner sees the settings screen editable', function () {
    $this->actingAs($this->owner)
        ->get('/configuracoes')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/settings/index')
            ->where('canEdit', true)
            ->where('settings.brand_color', '#2563EB')
            ->where('settings.currency', 'BRL'));
});
