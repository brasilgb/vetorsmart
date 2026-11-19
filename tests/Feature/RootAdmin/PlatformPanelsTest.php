<?php

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * PANELS-05: account actions in Usuários e Acessos, and the IA e Créditos and Sites e Domínios screens with real data.
 */
beforeEach(function () {
    Notification::fake();

    $this->root = User::factory()->rootAdmin()->create();
    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total']);
    $this->member = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->member->id]);
});

test('the user list shows the e-mail confirmation state of each account', function () {
    $legacy = User::factory()->unverified()->create(['email_verification_legacy_at' => now()]);
    $pending = User::factory()->unverified()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $legacy->id]);
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $pending->id]);

    $this->actingAs($this->root)->get('/rootadmin/users')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('memberships.data', fn ($rows) => collect($rows)->pluck('email_state', 'user_id')->all() === [
            $pending->id => 'pending',
            $legacy->id => 'legacy',
            $this->member->id => 'verified',
        ]));
});

test('suspending an account needs a reason, closes the access in every company and is audited', function () {
    $this->actingAs($this->root)->post("/rootadmin/users/{$this->member->id}/suspender", ['reason' => ''])
        ->assertSessionHasErrors(['reason' => 'Informe o motivo da suspensão (5 a 300 caracteres).']);
    expect($this->member->fresh()->status)->toBe(UserStatus::Active);

    $this->actingAs($this->root)->post("/rootadmin/users/{$this->member->id}/suspender", ['reason' => 'Uso indevido da conta'])->assertRedirect();

    expect($this->member->fresh()->status)->toBe(UserStatus::Suspended)
        ->and(AuditLog::query()->where('action', 'platform.user_suspended')->sole()->metadata['reason'])->toBe('Uso indevido da conta');
    $this->actingAs($this->member->fresh())->get('/clientes')->assertRedirect('/login');
});

test('a RootAdmin cannot suspend their own account', function () {
    $this->actingAs($this->root)->post("/rootadmin/users/{$this->root->id}/suspender", ['reason' => 'Teste de proteção'])
        ->assertSessionHasErrors(['reason' => 'Você não pode suspender a própria conta.']);

    expect($this->root->fresh()->status)->toBe(UserStatus::Active);
});

test('a suspended account can be reactivated, with audit', function () {
    $this->member->forceFill(['status' => UserStatus::Suspended])->save();

    $this->actingAs($this->root)->post("/rootadmin/users/{$this->member->id}/reativar")->assertRedirect();

    expect($this->member->fresh()->status)->toBe(UserStatus::Active)
        ->and(AuditLog::query()->where('action', 'platform.user_reactivated')->count())->toBe(1);
});

test('the confirmation link is resent only to an unconfirmed account', function () {
    $pending = User::factory()->unverified()->create(['email_verification_legacy_at' => now()]);

    $this->actingAs($this->root)->post("/rootadmin/users/{$pending->id}/reenviar-confirmacao")->assertRedirect();
    Notification::assertSentTo($pending, VerifyEmailNotification::class);

    $this->actingAs($this->root)->post("/rootadmin/users/{$this->member->id}/reenviar-confirmacao")
        ->assertSessionHasErrors(['user' => 'Este e-mail já está confirmado.']);
    Notification::assertNotSentTo($this->member, VerifyEmailNotification::class);
});

test('company users cannot reach the account actions nor the new screens', function (string $method, string $path) {
    $this->actingAs($this->member)->{$method}(str_replace('{user}', (string) $this->root->id, $path), ['reason' => 'Tentativa indevida'])->assertForbidden();

    expect($this->root->fresh()->status)->toBe(UserStatus::Active);
})->with([
    'suspender' => ['post', '/rootadmin/users/{user}/suspender'],
    'reativar' => ['post', '/rootadmin/users/{user}/reativar'],
    'reenviar' => ['post', '/rootadmin/users/{user}/reenviar-confirmacao'],
    'IA e Créditos' => ['get', '/rootadmin/ia'],
    'Sites e Domínios' => ['get', '/rootadmin/sites'],
]);

test('IA e Créditos shows the real usage of the month per company, without homologation in the totals', function () {
    $homolog = Tenant::factory()->create(['is_homolog' => true]);
    DB::table('assistant_usage')->insert([
        ['tenant_id' => $this->tenant->id, 'usage_date' => now()->startOfMonth()->toDateString(), 'messages' => 40, 'provider_calls' => 3, 'provider_failures' => 1, 'tokens' => 1200, 'created_at' => now(), 'updated_at' => now()],
        ['tenant_id' => $this->tenant->id, 'usage_date' => now()->subMonthNoOverflow()->toDateString(), 'messages' => 999, 'provider_calls' => 9, 'provider_failures' => 0, 'tokens' => 9999, 'created_at' => now(), 'updated_at' => now()],
        ['tenant_id' => $homolog->id, 'usage_date' => now()->toDateString(), 'messages' => 500, 'provider_calls' => 0, 'provider_failures' => 0, 'tokens' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->actingAs($this->root)->get('/rootadmin/ia')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('totals.messages', 40)
            ->where('totals.provider_calls', 3)
            ->where('totals.tokens', 1200)
            ->where('rows', fn ($rows) => collect($rows)->firstWhere('id', $this->tenant->id)['provider_failures'] === 1
                && collect($rows)->firstWhere('id', $homolog->id)['is_homolog'] === true));
});

test('Sites e Domínios lists each site with its addresses and never shows the verification token', function () {
    DB::table('tenant_websites')->insert(['tenant_id' => $this->tenant->id, 'name' => 'Clima Total', 'status' => 'published', 'draft_content' => '{}', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('tenant_domains')->insert([
        ['tenant_id' => $this->tenant->id, 'hostname' => 'climatotal.com.br', 'kind' => 'custom', 'status' => 'pending', 'verification_token' => 'token-secreto-123', 'is_primary' => false, 'created_at' => now(), 'updated_at' => now()],
        ['tenant_id' => $this->tenant->id, 'hostname' => 'clima-total.vetorsmart.com.br', 'kind' => 'subdomain', 'status' => 'verified', 'verification_token' => null, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $response = $this->actingAs($this->root)->get('/rootadmin/sites')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('counts.published', 1)
            ->where('counts.custom_domains_pending', 1)
            ->where('websites.data.0.domains.0.hostname', 'clima-total.vetorsmart.com.br')
            ->where('websites.data.0.domains.1.status', 'pending'));

    expect($response->getContent())->not->toContain('token-secreto-123');
});
