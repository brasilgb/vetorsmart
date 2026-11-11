<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $this->active = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ativo filtro']);
    $this->inactive = Service::factory()->inactive()->create(['tenant_id' => $this->tenant->id, 'name' => 'Inativo filtro']);
});

test('an empty situation filter lists active and inactive services', function () {
    $this->actingAs($this->owner)
        ->get(route('tenant.services.index', ['active' => '']))
        ->assertInertia(fn ($page) => $page->has('services.data', 2));
});

test('the situation filter separates active and inactive services', function () {
    $this->actingAs($this->owner)
        ->get(route('tenant.services.index', ['active' => '1']))
        ->assertInertia(fn ($page) => $page->has('services.data', 1)->where('services.data.0.name', 'Ativo filtro'));

    $this->actingAs($this->owner)
        ->get(route('tenant.services.index', ['active' => '0']))
        ->assertInertia(fn ($page) => $page->has('services.data', 1)->where('services.data.0.name', 'Inativo filtro'));
});
