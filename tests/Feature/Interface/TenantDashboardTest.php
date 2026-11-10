<?php

use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['phone' => null]);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
});

test('the dashboard shows only real indicators and counts only active records', function () {
    Customer::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
    Customer::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);
    Service::factory()->create(['tenant_id' => $this->tenant->id]);
    Service::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);
    Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    Professional::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('metrics.customers', 2)
            ->where('metrics.services', 1)
            ->where('metrics.professionals', 1)
            ->where('needsSetup', true));
});

test('a new company sees the setup checklist, not blocking screens', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->where('needsSetup', true)
            ->has('checklist', 3)
            ->where('checklist.0.done', false));
});

test('the checklist is complete when the company is set up', function () {
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id, 'description' => 'Instalação de ar']);
    $this->tenant->forceFill(['phone' => '11999990000'])->save();
    Service::factory()->create(['tenant_id' => $this->tenant->id]);
    Professional::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('needsSetup', false));
});

test('an operator also gets the dashboard without management data', function () {
    $operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $operator->id, 'role' => 'operator']);

    $this->actingAs($operator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('dashboard')->has('metrics'));
});
