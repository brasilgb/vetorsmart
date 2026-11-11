<?php

use App\Enums\PriceType;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $this->service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Instalação de chuveiro',
        'duration_minutes' => 60,
        'price' => 90,
        'price_type' => PriceType::Fixed,
        'active' => true,
    ]);

    $this->professionals = Professional::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'active' => true]);
    foreach ($this->professionals as $professional) {
        $professional->services()->attach($this->service->id, ['tenant_id' => $this->tenant->id]);
    }

    $this->actingAs($this->owner);
});

test('an invalid e-mail is reported in Portuguese', function () {
    $this->post(route('tenant.customers.store'), ['name' => 'Ana Lima', 'email' => 'ana@'])
        ->assertSessionHasErrors(['email' => 'Informe um e-mail válido, como nome@empresa.com.br.']);
});

test('an invalid professional e-mail is reported in Portuguese', function () {
    $this->post(route('tenant.professionals.store'), ['name' => 'Carlos', 'email' => 'carlos@'])
        ->assertSessionHasErrors(['email' => 'Informe um e-mail válido, como nome@empresa.com.br.']);
});

test('a service form that fails validation does not remove the professionals linked to it', function () {
    $this->patch(route('tenant.services.update', $this->service), [
        'name' => '',
        'duration_minutes' => 60,
        'price' => 90,
        'price_type' => PriceType::Fixed->value,
        'active' => true,
    ])->assertSessionHasErrors('name');

    expect($this->service->professionals()->count())->toBe(2);
});

test('saving a service without touching the professionals keeps its links', function () {
    $this->patch(route('tenant.services.update', $this->service), [
        'name' => 'Instalação de chuveiro elétrico',
        'duration_minutes' => 60,
        'price' => 95,
        'price_type' => PriceType::Fixed->value,
        'active' => true,
    ])->assertRedirect();

    expect($this->service->fresh()->name)->toBe('Instalação de chuveiro elétrico')
        ->and($this->service->professionals()->count())->toBe(2);
});
