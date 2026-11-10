<?php

use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total']);
    $this->owner = User::factory()->create(['email' => 'dono@example.com']);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
});

test('a valid owner signs in and lands on the tenant dashboard', function () {
    $this->post(route('login.store'), ['email' => 'dono@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($this->owner);
});

test('the same universal form sends the platform root admin to the console', function () {
    User::factory()->rootAdmin()->create(['email' => 'root@example.com']);

    $this->post(route('login.store'), ['email' => 'root@example.com', 'password' => 'password'])
        ->assertRedirect(route('rootadmin.dashboard', absolute: false));
});

test('an invalid password is refused with a generic message', function () {
    $response = $this->post(route('login.store'), ['email' => 'dono@example.com', 'password' => 'errada']);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(session('errors')->first('email'))->not->toContain('errada');
});

test('the session is regenerated when the user signs in', function () {
    $this->get(route('login'));
    $before = session()->getId();

    $this->post(route('login.store'), ['email' => 'dono@example.com', 'password' => 'password']);

    expect(session()->getId())->not->toBe($before);
});

test('logout ends the session and blocks the area', function () {
    $this->actingAs($this->owner);

    $this->post(route('logout'))->assertRedirect();

    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('repeated wrong passwords are rate limited', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['email' => 'dono@example.com', 'password' => 'errada']);
    }

    $this->post(route('login.store'), ['email' => 'dono@example.com', 'password' => 'password'])
        ->assertStatus(429);

    $this->assertGuest();
});

test('an external intended url is ignored after sign in', function () {
    $this->withSession(['url.intended' => 'https://evil.example.com/phish'])
        ->post(route('login.store'), ['email' => 'dono@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('a owner of a suspended company signs in but gets no company area', function () {
    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    $this->post(route('login.store'), ['email' => 'dono@example.com', 'password' => 'password']);

    $this->get(route('dashboard'))->assertForbidden();
});

test('a suspended user cannot sign in at all', function () {
    $this->owner->forceFill(['status' => 'suspended'])->save();

    $this->post(route('login.store'), ['email' => 'dono@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

test('a root admin without a company is not shown the tenant area', function () {
    $root = User::factory()->rootAdmin()->create();

    $this->actingAs($root)
        ->get(route('dashboard'))
        ->assertRedirect(route('rootadmin.dashboard', absolute: false));
});

test('signing in is not recorded as an administrative audit entry', function () {
    $this->post(route('login.store'), ['email' => 'dono@example.com', 'password' => 'password']);

    expect(AuditLog::query()->count())->toBe(0);
});
