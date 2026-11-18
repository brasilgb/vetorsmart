<?php

use App\Enums\BillingSource;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Models\BillingCharge;
use App\Models\BillingSetting;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\BillingCycle;
use App\Services\Billing\ChargeService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * User seats: three included in the plan plus the additional seats contracted. Registering users and contracting
 * seats are separate; Efí is always Http::fake.
 */
beforeEach(function () {
    Notification::fake();
    Http::fake(['*' => fn (HttpRequest $request) => Http::response([
        'access_token' => 'token', 'expires_in' => 3600,
        'txid' => basename((string) parse_url($request->url(), PHP_URL_PATH)), 'status' => 'ATIVA', 'pixCopiaECola' => 'pix',
    ])]);
    BillingSetting::current()->forceFill([
        'efi_pix_client_id' => 'id-teste', 'efi_pix_client_secret' => 'secret-teste',
        'efi_pix_certificate' => "-----BEGIN CERTIFICATE-----\nteste\n-----END CERTIFICATE-----", 'efi_pix_key' => 'chave-teste',
    ])->save();

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->subscription = app(SubscriptionService::class)->startTrial($this->tenant, BillingSource::System);
});

function newTeamMember(int $index): array
{
    return ['name' => "Pessoa {$index}", 'email' => "pessoa{$index}@example.test", 'password' => 'Senha-Forte-2026', 'password_confirmation' => 'Senha-Forte-2026', 'role' => 'operator'];
}

function fillSeats(Tenant $tenant, int $count): void
{
    for ($index = 0; $index < $count; $index++) {
        TenantUser::factory()->create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => MembershipStatus::Active]);
    }
}

test('a user beyond the contracted seats is refused with the reason', function () {
    fillSeats($this->tenant, 2);

    $this->actingAs($this->owner)->post('/equipe', newTeamMember(1))
        ->assertSessionHasErrors(['seats' => 'Todas as 3 vagas de usuário estão em uso. Desative um usuário ou contrate vagas adicionais em Minha Assinatura.']);

    expect(User::query()->where('email', 'pessoa1@example.test')->exists())->toBeFalse();
});

test('replacing a user inside the seats is allowed and creates no charge', function () {
    fillSeats($this->tenant, 2);
    $leaving = TenantUser::query()->where('tenant_id', $this->tenant->id)->where('role', 'operator')->firstOrFail();

    $this->actingAs($this->owner)->delete("/equipe/{$leaving->id}")->assertRedirect();
    $this->actingAs($this->owner)->post('/equipe', newTeamMember(1))->assertSessionHasNoErrors();

    expect(BillingCharge::query()->count())->toBe(0)
        ->and($this->subscription->fresh()->extra_seats)->toBe(0);
});

test('reactivating a user beyond the seats is refused', function () {
    fillSeats($this->tenant, 1);
    $inactive = TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => User::factory()->create()->id, 'status' => MembershipStatus::Inactive]);
    fillSeats($this->tenant, 1);

    $this->actingAs($this->owner)->post("/equipe/{$inactive->id}/ativar")->assertSessionHasErrors('seats');

    expect($inactive->fresh()->status)->toBe(MembershipStatus::Inactive);
});

test('a contracted seat allows one more user at once, without changing an open charge', function () {
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    fillSeats($this->tenant, 2);

    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 1])->assertRedirect('/assinatura');
    $this->actingAs($this->owner)->post('/equipe', newTeamMember(1))->assertSessionHasNoErrors();

    expect($charge->fresh()->amount_cents)->toBe(13990)
        ->and($this->subscription->fresh()->extra_seats)->toBe(1);
});

test('with a paid period running a seat reduction waits for the next period and its charge', function () {
    app(SubscriptionService::class)->choosePaymentMethod($this->subscription, PaymentMethod::Pix, $this->owner);
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 2]);
    $charge = app(ChargeService::class)->openFor($this->subscription->fresh(), PaymentMethod::Pix);
    app(ChargeService::class)->settleManually($charge, User::factory()->rootAdmin()->create(), 'Pagamento de homologação');

    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 0])->assertRedirect('/assinatura');

    $subscription = $this->subscription->fresh();
    expect($charge->amount_cents)->toBe(13990 + 2 * 1990)
        ->and($subscription->extra_seats)->toBe(2)
        ->and($subscription->scheduled_extra_seats)->toBe(0);

    $this->travelTo($charge->period_end->copy()->subDays(3));
    app(BillingCycle::class)->run();

    $renewal = BillingCharge::query()->where('id', '!=', $charge->id)->sole();
    expect($renewal->amount_cents)->toBe(13990)
        ->and($renewal->extra_users)->toBe(0)
        ->and($this->subscription->fresh()->extra_seats)->toBe(0);
});

test('while a reduction waits, activations respect the reduced limit', function () {
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 1]);
    app(ChargeService::class)->openFor($this->subscription->fresh(), PaymentMethod::Pix);
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 0]);
    fillSeats($this->tenant, 2);

    $this->actingAs($this->owner)->post('/equipe', newTeamMember(1))->assertSessionHasErrors('seats');
});

test('seats cannot be reduced below the active users', function () {
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 2]);
    fillSeats($this->tenant, 4);

    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 1])
        ->assertSessionHasErrors(['extra_seats' => 'Há 5 usuários ativos. Desative usuários até caberem em 4 vagas antes de reduzir.']);

    expect($this->subscription->fresh()->extra_seats)->toBe(2);
});

test('only the owner contracts seats', function () {
    $admin = User::factory()->create();
    TenantUser::factory()->admin()->create(['tenant_id' => $this->tenant->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)->post('/assinatura/vagas', ['extra_seats' => 3])->assertForbidden();

    expect($this->subscription->fresh()->extra_seats)->toBe(0);
});

test('a company without subscription has no user limit', function () {
    $legacy = Tenant::factory()->create();
    $legacyOwner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $legacy->id, 'user_id' => $legacyOwner->id]);
    fillSeats($legacy, 5);

    $this->actingAs($legacyOwner)->post('/equipe', newTeamMember(1))->assertSessionHasNoErrors();
});
