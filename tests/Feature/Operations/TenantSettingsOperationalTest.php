<?php

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['phone' => '1133334444', 'timezone' => 'America/Sao_Paulo']);
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id, 'business_name' => 'Clima Total']);

    $this->owner = User::factory()->create();
    $this->operator = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id, 'role' => 'operator']);
});

test('settings expose defaults and read phone and timezone from the tenant record', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/settings')
        ->assertOk()
        ->assertJsonPath('data.business_name', 'Clima Total')
        ->assertJsonPath('data.phone', '1133334444')
        ->assertJsonPath('data.timezone', 'America/Sao_Paulo')
        ->assertJsonPath('data.service_modes', [])
        ->assertJsonPath('data.confirmation_policy', 'manual');
});

test('operational settings are saved and phone and timezone are written to the tenant', function () {
    $this->actingAs($this->owner)
        ->patchJson('/api/v1/tenant/settings', [
            'business_name' => 'Clima Total Ltda',
            'description' => 'Instalação e manutenção de ar-condicionado',
            'phone' => '(11) 3333-4444',
            'timezone' => 'America/Manaus',
            'service_modes' => ['in_person', 'customer_location'],
            'welcome_message' => 'Olá! Como podemos ajudar?',
            'confirmation_policy' => 'customer_confirmation',
        ])
        ->assertOk()
        ->assertJsonPath('data.business_name', 'Clima Total Ltda')
        ->assertJsonPath('data.service_modes', ['in_person', 'customer_location'])
        ->assertJsonPath('data.confirmation_policy', 'customer_confirmation')
        ->assertJsonPath('data.phone', '(11) 3333-4444');

    $this->tenant->refresh();
    $setting = TenantSetting::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

    expect($this->tenant->timezone)->toBe('America/Manaus')
        ->and($this->tenant->phone)->toBe('(11) 3333-4444')
        ->and($setting->welcome_message)->toBe('Olá! Como podemos ajudar?');
});

test('phone and timezone are not duplicated in the settings table', function () {
    $columns = collect(Schema::getColumnListing('tenant_settings'));

    expect($columns->contains('phone'))->toBeFalse()
        ->and($columns->contains('timezone'))->toBeFalse();
});

test('invalid service modes, timezones and confirmation policies are refused', function () {
    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', ['service_modes' => ['drone']])
        ->assertUnprocessable()->assertJsonValidationErrors('service_modes.0');

    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', ['service_modes' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('service_modes');

    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', ['timezone' => 'Mars/Olympus'])
        ->assertUnprocessable()->assertJsonValidationErrors('timezone');

    $this->actingAs($this->owner)->patchJson('/api/v1/tenant/settings', ['confirmation_policy' => 'sempre'])
        ->assertUnprocessable()->assertJsonValidationErrors('confirmation_policy');
});

test('an operator can read settings but cannot change them', function () {
    $this->actingAs($this->operator)->getJson('/api/v1/tenant/settings')->assertOk();

    $this->actingAs($this->operator)
        ->patchJson('/api/v1/tenant/settings', ['welcome_message' => 'Alterado'])
        ->assertForbidden();
});
