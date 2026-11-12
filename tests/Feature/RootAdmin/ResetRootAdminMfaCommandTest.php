<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

const MFA_RESET_REASON = 'Administrador perdeu o aplicativo e os códigos, identidade confirmada por e-mail corporativo.';

/**
 * Stores a session row for the user, as the database session driver does.
 */
function sessionFor(User $user): void
{
    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $user->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'test',
        'last_activity' => time(),
    ]);
}

test('the operator revokes the factor, ends the sessions and the next sign-in must enroll again', function () {
    $root = User::factory()->rootAdmin()->withConfirmedTwoFactor()->create(['email' => 'root.reset@example.com']);
    $rememberBefore = $root->remember_token;
    sessionFor($root);
    sessionFor($root);

    $this->artisan('platform:mfa-reset', [
        'email' => 'root.reset@example.com',
        '--reason' => MFA_RESET_REASON,
        '--confirm-email' => 'root.reset@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $root->refresh();

    expect($root->two_factor_secret)->toBeNull()
        ->and($root->two_factor_recovery_codes)->toBeNull()
        ->and($root->two_factor_confirmed_at)->toBeNull()
        ->and($root->remember_token)->not->toBe($rememberBefore)
        ->and(DB::table('sessions')->where('user_id', $root->id)->count())->toBe(0);

    $this->actingAs($root)
        ->get('/rootadmin')
        ->assertRedirect(route('security.edit', absolute: false));
});

test('the reset is audited with the justification and without any secret', function () {
    $root = User::factory()->rootAdmin()->withConfirmedTwoFactor()->create(['email' => 'root.audit@example.com']);

    $this->artisan('platform:mfa-reset', [
        'email' => 'root.audit@example.com',
        '--reason' => MFA_RESET_REASON,
        '--confirm-email' => 'root.audit@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $entry = AuditLog::query()->where('action', 'mfa.reset_by_operator')->firstOrFail();
    $serialized = json_encode($entry->getAttributes());

    expect($entry->auditable_id)->toBe($root->id)
        ->and($entry->metadata['reason'])->toBe(MFA_RESET_REASON)
        ->and($serialized)->not->toContain(UserFactory::TWO_FACTOR_SECRET)
        ->and($serialized)->not->toContain(UserFactory::TWO_FACTOR_RECOVERY_CODES[0]);
});

test('a common user or an unknown e-mail is refused and nothing changes', function () {
    $common = User::factory()->withConfirmedTwoFactor()->create(['email' => 'comum.reset@example.com']);

    $this->artisan('platform:mfa-reset', [
        'email' => 'comum.reset@example.com',
        '--reason' => MFA_RESET_REASON,
        '--confirm-email' => 'comum.reset@example.com',
        '--no-interaction' => true,
    ])->assertFailed();

    $this->artisan('platform:mfa-reset', [
        'email' => 'inexistente@example.com',
        '--reason' => MFA_RESET_REASON,
        '--confirm-email' => 'inexistente@example.com',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($common->fresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'mfa.reset_by_operator')->exists())->toBeFalse();
});

test('a missing or short justification is refused', function () {
    $root = User::factory()->rootAdmin()->withConfirmedTwoFactor()->create(['email' => 'root.motivo@example.com']);

    $this->artisan('platform:mfa-reset', [
        'email' => 'root.motivo@example.com',
        '--reason' => 'curto',
        '--confirm-email' => 'root.motivo@example.com',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($root->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('without interaction the e-mail must be repeated to confirm the operation', function () {
    $root = User::factory()->rootAdmin()->withConfirmedTwoFactor()->create(['email' => 'root.confirma.reset@example.com']);

    $this->artisan('platform:mfa-reset', [
        'email' => 'root.confirma.reset@example.com',
        '--reason' => MFA_RESET_REASON,
        '--no-interaction' => true,
    ])->assertFailed();

    $this->artisan('platform:mfa-reset', [
        'email' => 'root.confirma.reset@example.com',
        '--reason' => MFA_RESET_REASON,
        '--confirm-email' => 'outro@example.com',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($root->fresh()->two_factor_confirmed_at)->not->toBeNull();
});
