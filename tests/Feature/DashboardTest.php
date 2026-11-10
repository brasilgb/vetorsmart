<?php

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated tenant members can visit the dashboard', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
