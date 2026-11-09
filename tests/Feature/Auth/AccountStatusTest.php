<?php

use App\Enums\MembershipStatus;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

test('a user without verified email can sign in and reach the dashboard', function () {
    User::factory()->unverified()->create([
        'email' => 'sem-verificacao@example.com',
    ]);

    $this->post(route('login.store'), [
        'email' => 'sem-verificacao@example.com',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticated();
    $this->get(route('dashboard'))->assertOk();
});

test('a suspended user cannot sign in', function () {
    User::factory()->suspended()->create([
        'email' => 'suspenso@example.com',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'suspenso@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a user suspended after signing in is signed out on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->forceFill(['status' => 'suspended'])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a member of a suspended tenant gets no access to tenant data', function () {
    $tenant = Tenant::factory()->suspended()->create();
    $user = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/v1/tenant/settings')
        ->assertForbidden();
});

test('an inactive membership grants no tenant access', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::factory()->owner()->inactive()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/v1/tenant/settings')
        ->assertForbidden();

    expect(TenantUser::query()->value('status'))->toBe(MembershipStatus::Inactive)
        ->and(TenantUser::query()->value('role'))->toBe(TenantRole::Owner);
});

test('a cancelled tenant grants no access, although no operation sets this status yet', function () {
    $tenant = Tenant::factory()->create(['status' => 'cancelled']);
    $user = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/v1/tenant/settings')
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson('/api/v1/auth/tenants')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
