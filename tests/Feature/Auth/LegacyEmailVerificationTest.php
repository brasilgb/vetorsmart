<?php

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;

/**
 * Accounts that existed before e-mail confirmation keep their access (legacy) without being marked as confirmed;
 * sensitive operations require the real confirmation; the confirmation link is signed, expires and is resent within
 * limits.
 */
beforeEach(function () {
    Notification::fake();

    $this->tenant = Tenant::factory()->create();
    $this->legacy = User::factory()->unverified()->create(['email_verification_legacy_at' => now()->subMonth()]);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->legacy->id]);
});

function confirmationLink(User $user, ?CarbonInterface $expires = null): string
{
    return URL::temporarySignedRoute('verification.verify', $expires ?? now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
}

test('a legacy account keeps its panel without being marked as confirmed, and is reminded to confirm', function () {
    $this->actingAs($this->legacy)->get('/clientes')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.emailConfirmationPending', true));

    expect($this->legacy->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a confirmed account is not reminded', function () {
    $user = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get('/clientes')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.emailConfirmationPending', false));
});

test('an unconfirmed account without legacy access goes to the confirmation, as any new account', function () {
    $user = User::factory()->unverified()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get('/clientes')->assertRedirect(route('verification.notice'));
});

test('sensitive operations require the real confirmation from a legacy account', function (string $method, string $path) {
    $this->actingAs($this->legacy)->{$method}($path, ['name' => 'X', 'email' => 'x@example.test', 'role' => 'operator', 'method' => 'pix'])
        ->assertRedirect(route('verification.notice'));
})->with([
    'adicionar usuário' => ['post', '/equipe'],
    'contratar assinatura' => ['post', '/assinatura/contratar'],
    'alterar vagas' => ['post', '/assinatura/vagas'],
]);

test('the RootAdmin role alone does not open the console to a legacy account', function () {
    $this->legacy->forceFill(['is_root_admin' => true])->save();

    $this->actingAs($this->legacy)->get('/rootadmin')->assertRedirect(route('verification.notice'));
    $this->actingAs($this->legacy)->getJson('/api/v1/admin/tenants')->assertForbidden();
});

test('a legacy account confirms through the signed link and becomes confirmed', function () {
    $this->actingAs($this->legacy)->get(confirmationLink($this->legacy))->assertRedirect();

    expect($this->legacy->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('an expired or tampered link confirms nothing and explains the refusal', function (string $link) {
    $this->actingAs($this->legacy)->get($link)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('verification_error');

    expect($this->legacy->fresh()->hasVerifiedEmail())->toBeFalse();
})->with([
    'expirado' => fn () => confirmationLink($this->legacy, now()->subMinute()),
    'adulterado' => fn () => confirmationLink($this->legacy).'x',
]);

test('the confirmation link is resent within limits', function () {
    foreach (range(1, 3) as $attempt) {
        $this->actingAs($this->legacy)->post(route('verification.send'))->assertRedirect();
    }

    $this->actingAs($this->legacy)->post(route('verification.send'))->assertTooManyRequests();

    Notification::assertSentToTimes($this->legacy, VerifyEmailNotification::class, 3);
});

test('changing the e-mail ends the legacy access: the new address must be confirmed', function () {
    $this->actingAs($this->legacy)->patch('/settings/profile', ['name' => $this->legacy->name, 'email' => 'novo@example.test'])->assertRedirect();

    $user = $this->legacy->fresh();
    expect($user->email_verification_legacy_at)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();
    $this->actingAs($user)->get('/clientes')->assertRedirect(route('verification.notice'));
});

test('the legacy access ends after the configured deadline, and an invalid deadline locks no one out', function () {
    config(['platform.legacy_email_grace_until' => now()->subDay()->toDateString()]);
    $this->actingAs($this->legacy)->get('/clientes')->assertRedirect(route('verification.notice'));

    config(['platform.legacy_email_grace_until' => 'data-invalida']);
    $this->actingAs($this->legacy)->get('/clientes')->assertOk();
});
