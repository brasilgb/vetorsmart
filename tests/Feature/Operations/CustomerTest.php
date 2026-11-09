<?php

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
});

test('a customer can be registered with only a name', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/customers', ['name' => 'Maria Silva'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Maria Silva')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.person_type', 'individual');

    expect(Customer::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('phone and document are stored as digits and e-mail in lowercase', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/customers', [
            'name' => 'Empresa X',
            'person_type' => 'company',
            'document' => '12.345.678/0001-90',
            'email' => 'Contato@EmpresaX.com.br',
            'phone' => '(11) 98765-4321',
        ])
        ->assertSuccessful();

    $customer = Customer::query()->firstOrFail();

    expect($customer->document)->toBe('12345678000190')
        ->and($customer->phone)->toBe('11987654321')
        ->and($customer->email)->toBe('contato@empresax.com.br');
});

test('name is required and invalid enum values are refused', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/customers', ['phone' => '11999999999'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/customers', ['name' => 'X', 'person_type' => 'alien'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('person_type');

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/customers', ['name' => 'X', 'email' => 'nao-e-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('a customer with the same phone or e-mail in the same tenant is not duplicated', function () {
    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Ana',
        'phone' => '11987654321',
        'email' => 'ana@example.com',
    ]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/customers', ['name' => 'Ana Outra', 'phone' => '(11) 98765-4321'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('duplicate');

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/customers', ['name' => 'Ana Outra', 'email' => 'ANA@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('duplicate');

    expect(Customer::query()->count())->toBe(1);
});

test('the same phone can exist in another tenant', function () {
    $other = Tenant::factory()->create();
    Customer::factory()->create(['tenant_id' => $other->id, 'phone' => '11987654321']);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/tenant/customers', ['name' => 'Ana', 'phone' => '11987654321'])
        ->assertSuccessful();
});

test('the customer list is paginated', function () {
    Customer::factory()->count(25)->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/customers')
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/customers?per_page=10&page=3')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

test('customers can be searched by name, e-mail and phone digits', function () {
    Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Carlos Pereira', 'email' => 'carlos@x.com', 'phone' => '11911112222']);
    Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beatriz Lima', 'email' => 'bia@x.com', 'phone' => '21933334444']);

    $this->actingAs($this->owner)->getJson('/api/v1/tenant/customers?q=carlos')
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Carlos Pereira');

    $this->actingAs($this->owner)->getJson('/api/v1/tenant/customers?q=bia@x')
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Beatriz Lima');

    $this->actingAs($this->owner)->getJson('/api/v1/tenant/customers?q=(21) 93333-4444')
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Beatriz Lima');
});

test('a customer can be shown and partially updated', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Antigo']);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/customers/'.$customer->id)
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id);

    $this->actingAs($this->owner)
        ->patchJson('/api/v1/tenant/customers/'.$customer->id, ['city' => 'Campinas', 'state' => 'SP'])
        ->assertOk()
        ->assertJsonPath('data.city', 'Campinas')
        ->assertJsonPath('data.name', 'Antigo');
});

test('deactivating a customer keeps the record', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->deleteJson('/api/v1/tenant/customers/'.$customer->id)
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    expect($customer->fresh()->status)->toBe(CustomerStatus::Inactive);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/customers?status=inactive')
        ->assertJsonCount(1, 'data');
});

test('an invalid status filter is refused', function () {
    $this->actingAs($this->owner)
        ->getJson('/api/v1/tenant/customers?status=apagado')
        ->assertUnprocessable();
});
