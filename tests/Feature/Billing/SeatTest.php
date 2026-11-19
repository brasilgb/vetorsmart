<?php

use App\Enums\BillingSource;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\BillingCharge;
use App\Models\BillingSetting;
use App\Models\Subscription;
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
 * User seats: three included in the plan plus the additional seats contracted. No complementary charge exists, so
 * a seat is never active before it is paid. Efí is always Http::fake.
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
    $this->root = User::factory()->rootAdmin()->create();
});

function newTeamMember(int $index): array
{
    return ['name' => "Pessoa {$index}", 'email' => "pessoa{$index}@example.test", 'password' => 'Senha-Forte-2026', 'password_confirmation' => 'Senha-Forte-2026', 'role' => 'operator'];
}

function fillSeats(Tenant $tenant, int $count, MembershipStatus $status = MembershipStatus::Active): void
{
    for ($index = 0; $index < $count; $index++) {
        TenantUser::factory()->create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => $status]);
    }
}

/**
 * Contract the given cycle during the trial and pay it: a paid period is running afterwards.
 */
function paidPeriod(Subscription $subscription, User $owner, User $root, string $interval = 'monthly'): BillingCharge
{
    test()->actingAs($owner)->post('/assinatura/contratar', ['method' => 'pix', 'interval' => $interval]);
    $charge = BillingCharge::query()->where('subscription_id', $subscription->id)->latest('id')->firstOrFail();
    app(ChargeService::class)->settleManually($charge, $root, 'Pagamento de homologação');

    return $charge;
}

test('a user beyond the seats is refused with the reason', function () {
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

test('registered inactive users are never charged', function () {
    fillSeats($this->tenant, 5, MembershipStatus::Inactive);

    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);

    expect($charge->extra_users)->toBe(0)
        ->and($charge->amount_cents)->toBe(13990);
});

test('in the trial without an open charge more seats apply at once and go into the first charge', function () {
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 1])->assertRedirect('/assinatura');
    fillSeats($this->tenant, 2);

    $this->actingAs($this->owner)->post('/equipe', newTeamMember(1))->assertSessionHasNoErrors();

    $charge = app(ChargeService::class)->openFor($this->subscription->fresh(), PaymentMethod::Pix);
    expect($charge->extra_users)->toBe(1)
        ->and($charge->amount_cents)->toBe(13990 + 1990);
});

test('with a paid period, more seats are charged in the renewal of each cycle and become active only when it is paid', function (string $interval, int $price, int $months) {
    paidPeriod($this->subscription, $this->owner, $this->root, $interval);
    fillSeats($this->tenant, 2);

    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 1])->assertRedirect('/assinatura');

    expect($this->subscription->fresh()->extra_seats)->toBe(0)
        ->and($this->subscription->fresh()->scheduled_extra_seats)->toBe(1);
    $this->actingAs($this->owner)->post('/equipe', newTeamMember(1))->assertSessionHasErrors('seats');

    $this->travelTo($this->subscription->fresh()->current_period_end->copy()->subDays(3));
    app(BillingCycle::class)->run();
    $renewal = BillingCharge::query()->where('subscription_id', $this->subscription->id)->latest('id')->firstOrFail();

    expect($renewal->extra_users)->toBe(1)
        ->and($renewal->amount_cents)->toBe($price + 1990 * $months);
    $this->actingAs($this->owner)->post('/equipe', newTeamMember(2))->assertSessionHasErrors('seats');

    app(ChargeService::class)->settleManually($renewal, $this->root, 'Pagamento de homologação');

    expect($this->subscription->fresh()->extra_seats)->toBe(1)
        ->and($this->subscription->fresh()->scheduled_extra_seats)->toBeNull();
    $this->actingAs($this->owner)->post('/equipe', newTeamMember(3))->assertSessionHasNoErrors();
})->with([
    'mensal' => ['monthly', 13990, 1],
    'semestral' => ['semiannual', 71940, 6],
    'anual' => ['annual', 131880, 12],
]);

test('an open charge is never changed: the seats go into the following charge', function () {
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);

    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 2]);

    expect($charge->fresh()->amount_cents)->toBe(13990)
        ->and($charge->fresh()->extra_users)->toBe(0)
        ->and($this->subscription->fresh()->scheduled_extra_seats)->toBe(2);

    app(ChargeService::class)->settleManually($charge, $this->root, 'Pagamento de homologação');

    expect($this->subscription->fresh()->extra_seats)->toBe(0)
        ->and($this->subscription->fresh()->scheduled_extra_seats)->toBe(2);
});

test('a renewal left unpaid never activates the seats, and no change is accepted while payment is pending', function () {
    app(SubscriptionService::class)->choosePaymentMethod($this->subscription, PaymentMethod::Pix, $this->owner);
    paidPeriod($this->subscription, $this->owner, $this->root);
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 1]);

    $this->travelTo($this->subscription->fresh()->current_period_end->copy()->addDay());
    app(BillingCycle::class)->run();

    $subscription = $this->subscription->fresh();
    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->extra_seats)->toBe(0);

    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 3])
        ->assertSessionHasErrors(['extra_seats' => 'Regularize a assinatura (pagamento pendente, suspensão ou cancelamento) antes de alterar as vagas.']);
});

test('asking twice for the same seats records one change and charges them once', function () {
    paidPeriod($this->subscription, $this->owner, $this->root);

    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 2]);
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 2]);

    expect($this->subscription->events()->where('type', 'seats_change_scheduled')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'subscription.seats_changed')->count())->toBe(1);

    $this->travelTo($this->subscription->fresh()->current_period_end->copy()->subDays(3));
    app(BillingCycle::class)->run();
    app(BillingCycle::class)->run();

    expect(BillingCharge::query()->where('subscription_id', $this->subscription->id)->where('extra_users', 2)->count())->toBe(1);
});

test('a reduction waits for the paid renewal, and activations respect it meanwhile', function () {
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 2]);
    paidPeriod($this->subscription, $this->owner, $this->root);
    expect($this->subscription->fresh()->extra_seats)->toBe(2);

    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 0])->assertRedirect('/assinatura');
    fillSeats($this->tenant, 2);
    $this->actingAs($this->owner)->post('/equipe', newTeamMember(1))->assertSessionHasErrors('seats');

    $this->travelTo($this->subscription->fresh()->current_period_end->copy()->subDays(3));
    app(BillingCycle::class)->run();
    $renewal = BillingCharge::query()->where('subscription_id', $this->subscription->id)->latest('id')->firstOrFail();
    app(ChargeService::class)->settleManually($renewal, $this->root, 'Pagamento de homologação');

    expect($renewal->amount_cents)->toBe(13990)
        ->and($this->subscription->fresh()->extra_seats)->toBe(0);
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

test('Minha Assinatura tells which seat rule applies now', function () {
    $this->actingAs($this->owner)->get('/assinatura')
        ->assertInertia(fn ($page) => $page->where('subscription.seats_change_mode', 'now'));

    app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);

    $this->actingAs($this->owner)->get('/assinatura')
        ->assertInertia(fn ($page) => $page->where('subscription.seats_change_mode', 'next_charge'));
});
