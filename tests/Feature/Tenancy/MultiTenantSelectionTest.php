<?php

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->first = Tenant::factory()->create(['name' => 'Empresa Um']);
    $this->second = Tenant::factory()->create(['name' => 'Empresa Dois']);

    TenantUser::factory()->owner()->create(['tenant_id' => $this->first->id, 'user_id' => $this->user->id]);
    TenantUser::factory()->admin()->create(['tenant_id' => $this->second->id, 'user_id' => $this->user->id]);
});

test('a user with several companies receives all of them for the switcher', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tenants', 2)
            ->where('tenant.name', 'Empresa Um'));
});

test('the user can choose another company they belong to', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/auth/switch-tenant', ['tenant_id' => $this->second->id])
        ->assertOk();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tenant.name', 'Empresa Dois'));
});

test('a company passed by a crafted id without membership is refused', function () {
    $stranger = Tenant::factory()->create(['name' => 'Estranha']);

    $this->actingAs($this->user)
        ->postJson('/api/v1/auth/switch-tenant', ['tenant_id' => $stranger->id])
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tenant.name', 'Empresa Um'));
});

test('the shared company list never exposes companies the user does not belong to', function () {
    Tenant::factory()->create(['name' => 'Alheia']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tenants', 2)
            ->where('tenants.0.name', 'Empresa Um')
            ->where('tenants.1.name', 'Empresa Dois'));
});
