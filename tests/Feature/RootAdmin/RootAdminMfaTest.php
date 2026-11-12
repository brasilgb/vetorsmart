<?php

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Factories\UserFactory;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create(['email' => 'dono.mfa@example.com']);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
});

/**
 * The code the authenticator app shows right now for the factory secret.
 */
function currentCode(): string
{
    return (new Google2FA)->getCurrentOtp(UserFactory::TWO_FACTOR_SECRET);
}

/**
 * A code from an earlier 30-second window, outside the accepted drift.
 */
function expiredCode(): string
{
    $google2fa = new Google2FA;

    return $google2fa->oathTotp(UserFactory::TWO_FACTOR_SECRET, $google2fa->getTimestamp() - 10);
}

function rootWithMfa(array $attributes = []): User
{
    return User::factory()->rootAdmin()->withConfirmedTwoFactor()->create(array_merge(['email' => 'root.mfa@example.com'], $attributes));
}

test('a global administrator without a confirmed second factor cannot reach the console or act on companies', function () {
    $root = User::factory()->rootAdmin()->create(['email' => 'root.sem.mfa@example.com']);

    $this->actingAs($root)
        ->get('/rootadmin/tenants')
        ->assertRedirect(route('security.edit', absolute: false));

    $this->actingAs($root)
        ->post("/rootadmin/tenants/{$this->tenant->id}/status", ['status' => 'suspended', 'confirm' => true])
        ->assertRedirect(route('security.edit', absolute: false));

    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Active);
});

test('enrollment stores an encrypted secret, requires a valid code and is audited without secrets', function () {
    $root = User::factory()->rootAdmin()->create(['email' => 'root.enroll@example.com']);

    $this->actingAs($root)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'))
        ->assertRedirect();

    $root->refresh();

    expect($root->two_factor_secret)->not->toBeNull()
        ->and($root->two_factor_confirmed_at)->toBeNull();

    $this->actingAs($root)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code', null, 'confirmTwoFactorAuthentication');

    expect($root->fresh()->two_factor_confirmed_at)->toBeNull();

    $secret = decrypt($root->fresh()->two_factor_secret);

    $this->actingAs($root)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect();

    expect($root->fresh()->two_factor_confirmed_at)->not->toBeNull();

    $actions = AuditLog::query()->where('user_id', $root->id)->pluck('action')->all();

    expect($actions)->toContain('mfa.enabled', 'mfa.confirmed');

    $serialized = json_encode(AuditLog::query()->get()->pluck('metadata'));

    expect($serialized)->not->toContain($secret)
        ->and($serialized)->not->toContain('recovery')
        ->and($serialized)->not->toContain('secret');
});

test('confirming the password never sends a global administrator to a stale intended URL', function () {
    $root = User::factory()->rootAdmin()->create(['email' => 'root.confirma@example.com']);

    $this->actingAs($root)
        ->withSession(['url.intended' => '/'])
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('rootadmin.dashboard', absolute: false));
});

test('an administrator who asked for a settings page after confirming the password returns to that page', function () {
    $root = rootWithMfa();

    $this->actingAs($root)
        ->withSession(['url.intended' => route('security.edit')])
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('security.edit'));
});

test('after enrollment the console opens and the security page reports the factor as active', function () {
    $root = rootWithMfa();

    $this->actingAs($root)
        ->get('/rootadmin/tenants')
        ->assertOk();

    $this->actingAs($root)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/security')
            ->where('twoFactor.confirmed', true)
            ->where('twoFactor.required', true));
});

test('a root administrator with a confirmed factor must pass the challenge after the password', function () {
    $root = rootWithMfa();

    $this->post(route('login.store'), ['email' => $root->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login', absolute: false));

    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => currentCode()])
        ->assertRedirect(route('rootadmin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($root);
});

test('a wrong code is refused and does not sign the administrator in', function () {
    $root = rootWithMfa();

    $this->post(route('login.store'), ['email' => $root->email, 'password' => 'password']);

    $this->post(route('two-factor.login.store'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('an expired code is refused, and a code cannot be reused for a second sign-in', function () {
    $root = rootWithMfa();

    $this->post(route('login.store'), ['email' => $root->email, 'password' => 'password']);

    $this->post(route('two-factor.login.store'), ['code' => expiredCode()])
        ->assertSessionHasErrors('code');

    $this->assertGuest();

    $code = currentCode();

    $this->post(route('login.store'), ['email' => $root->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['code' => $code])->assertRedirect();

    $this->post('/logout');

    $this->post(route('login.store'), ['email' => $root->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a valid recovery code signs the administrator in, and that same code is refused afterwards', function () {
    $root = rootWithMfa();

    $this->post(route('login.store'), ['email' => $root->email, 'password' => 'password']);

    $this->post(route('two-factor.login.store'), ['recovery_code' => UserFactory::TWO_FACTOR_RECOVERY_CODES[0]])
        ->assertRedirect(route('rootadmin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($root);

    $this->post('/logout');

    $this->post(route('login.store'), ['email' => $root->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['recovery_code' => UserFactory::TWO_FACTOR_RECOVERY_CODES[0]])
        ->assertSessionHasErrors('recovery_code');

    $this->assertGuest();
});

test('a tenant user, even with a confirmed factor, gets no access to the global area', function () {
    $tenantUserWithMfa = User::factory()->withConfirmedTwoFactor()->create(['email' => 'operador.mfa@example.com']);
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $tenantUserWithMfa->id, 'role' => TenantRole::Operator]);

    $this->actingAs($tenantUserWithMfa)
        ->get('/rootadmin')
        ->assertForbidden();

    $this->actingAs($tenantUserWithMfa)
        ->post("/rootadmin/tenants/{$this->tenant->id}/status", ['status' => 'suspended', 'confirm' => true])
        ->assertForbidden();

    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Active);
});

test('the challenge is rate limited per pending sign-in', function () {
    $root = rootWithMfa();

    $this->post(route('login.store'), ['email' => $root->email, 'password' => 'password']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('two-factor.login.store'), ['code' => '000000']);
    }

    $this->post(route('two-factor.login.store'), ['code' => currentCode()])
        ->assertStatus(429);

    $this->assertGuest();
});

test('logging out ends the administrative session', function () {
    $root = rootWithMfa();

    $this->actingAs($root)->get('/rootadmin')->assertOk();

    $this->post('/logout')->assertRedirect();

    $this->get('/rootadmin')->assertRedirect(route('login', absolute: false));
});

test('revoking the factor sends the administrator back to setup, and regenerated codes replace the old ones', function () {
    $root = rootWithMfa();

    $this->actingAs($root)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.regenerate-recovery-codes'))
        ->assertRedirect();

    expect(decrypt($root->fresh()->two_factor_recovery_codes))->not->toBe(json_encode(UserFactory::TWO_FACTOR_RECOVERY_CODES));

    expect(AuditLog::query()->where('user_id', $root->id)->where('action', 'mfa.recovery_codes_regenerated')->exists())->toBeTrue();

    $this->actingAs($root)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('two-factor.disable'))
        ->assertRedirect();

    expect($root->fresh()->hasConfirmedTwoFactor())->toBeFalse();

    $this->actingAs($root)
        ->get('/rootadmin')
        ->assertRedirect(route('security.edit', absolute: false));

    expect(AuditLog::query()->where('user_id', $root->id)->where('action', 'mfa.disabled')->exists())->toBeTrue();
});

test('a wrong code at enrollment is reported to a JSON client as a field error', function () {
    $root = User::factory()->rootAdmin()->create(['email' => 'root.json.mfa@example.com']);

    $this->actingAs($root)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'));

    $this->actingAs($root)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->postJson(route('two-factor.confirm'), ['code' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    expect($root->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('the two-factor secrets and codes are never serialized to the client', function () {
    $root = rootWithMfa();

    $serialized = json_encode($root->toArray());

    expect($serialized)->not->toContain('two_factor_secret')
        ->and($serialized)->not->toContain('two_factor_recovery_codes')
        ->and($serialized)->not->toContain(UserFactory::TWO_FACTOR_SECRET);

    expect($root->getRawOriginal('two_factor_secret'))->not->toBe(UserFactory::TWO_FACTOR_SECRET);
});
