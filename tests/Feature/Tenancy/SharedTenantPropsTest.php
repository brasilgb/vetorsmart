<?php

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->first = Tenant::factory()->create(['name' => 'Primeira']);
    $this->second = Tenant::factory()->create(['name' => 'Segunda']);
    $this->suspended = Tenant::factory()->suspended()->create(['name' => 'Suspensa']);

    TenantUser::factory()->owner()->create(['tenant_id' => $this->first->id, 'user_id' => $this->user->id]);
    TenantUser::factory()->create(['tenant_id' => $this->second->id, 'user_id' => $this->user->id]);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->suspended->id, 'user_id' => $this->user->id]);
});

test('the panel shares the company chosen in the session and only the accessible companies', function () {
    $this->actingAs($this->user)
        ->withSession(['current_tenant_id' => $this->second->id])
        ->get('/clientes')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tenant', ['id' => $this->second->id, 'name' => 'Segunda', 'role' => 'operator'])
            ->where('tenants', [
                ['id' => $this->first->id, 'name' => 'Primeira', 'role' => 'owner'],
                ['id' => $this->second->id, 'name' => 'Segunda', 'role' => 'operator'],
            ]));
});

test('a page outside the tenant routes shares the same company', function () {
    $this->actingAs($this->user)
        ->withSession(['current_tenant_id' => $this->second->id])
        ->get('/settings/profile')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tenant.id', $this->second->id));
});

test('a session pointing to an inaccessible company falls back to the first accessible one', function () {
    $this->actingAs($this->user)
        ->withSession(['current_tenant_id' => $this->suspended->id])
        ->get('/settings/profile')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tenant.id', $this->first->id)
            ->has('tenants', 2));
});
