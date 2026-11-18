<?php

use App\Enums\BillingInterval;
use App\Enums\BillingSource;
use App\Enums\ChargeStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Models\AuditLog;
use App\Models\BillingCharge;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\BillingCycle;
use App\Services\Billing\ChargeService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * Billing cycles of BILLING-01.1 with the provisional prices written by the migration (monthly R$ 139,90, semiannual
 * R$ 719,40, annual R$ 1.318,80, three users included, R$ 19,90 per extra user). Efí is always Http::fake.
 */
beforeEach(function () {
    Notification::fake();
    Http::fake(['*' => fn (HttpRequest $request) => Http::response([
        'access_token' => 'token', 'expires_in' => 3600,
        'txid' => basename((string) parse_url($request->url(), PHP_URL_PATH)), 'status' => 'ATIVA', 'pixCopiaECola' => 'pix-copia-e-cola',
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

function addMembers(Tenant $tenant, int $count, MembershipStatus $status = MembershipStatus::Active): void
{
    for ($index = 0; $index < $count; $index++) {
        TenantUser::factory()->create(['tenant_id' => $tenant->id, 'user_id' => User::factory()->create()->id, 'status' => $status]);
    }
}

function payCharge(BillingCharge $charge): void
{
    app(ChargeService::class)->settleManually($charge, User::factory()->rootAdmin()->create(), 'Pagamento de homologação');
}

test('Minha Assinatura shows the three cycles with total, monthly equivalent and savings', function () {
    $this->actingAs($this->owner)->get('/assinatura')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('offer.cycles.0', ['interval' => 'monthly', 'label' => 'Mensal', 'months' => 1, 'total_cents' => 13990, 'monthly_equivalent_cents' => 13990, 'savings_percent' => 0, 'total' => 'R$ 139,90', 'monthly_equivalent' => 'R$ 139,90'])
            ->where('offer.cycles.1.total', 'R$ 719,40')
            ->where('offer.cycles.1.monthly_equivalent', 'R$ 119,90')
            ->where('offer.cycles.1.savings_percent', 14)
            ->where('offer.cycles.2.total', 'R$ 1.318,80')
            ->where('offer.cycles.2.monthly_equivalent', 'R$ 109,90')
            ->where('offer.cycles.2.savings_percent', 21)
            ->where('offer.included_users', 3)
            ->where('subscription.next_charge.total', 'R$ 139,90'));
});

test('contracting a cycle charges its total for its number of months', function (string $interval, int $amount, int $months) {
    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'pix', 'interval' => $interval])->assertRedirect('/assinatura');

    $charge = BillingCharge::query()->sole();

    expect($charge->amount_cents)->toBe($amount)
        ->and($charge->billing_interval->value)->toBe($interval)
        ->and((int) round($charge->period_start->diffInMonths($charge->period_end)))->toBe($months)
        ->and($charge->status)->toBe(ChargeStatus::Waiting);
})->with([
    'mensal' => ['monthly', 13990, 1],
    'semestral' => ['semiannual', 71940, 6],
    'anual' => ['annual', 131880, 12],
]);

test('contracted additional seats are charged per month of the cycle; registered users do not change the amount', function () {
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 2])->assertRedirect('/assinatura');
    addMembers($this->tenant, 2, MembershipStatus::Inactive);

    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'pix', 'interval' => 'semiannual']);

    $charge = BillingCharge::query()->sole();

    expect($charge->extra_users)->toBe(2)
        ->and($charge->extra_users_amount_cents)->toBe(2 * 1990 * 6)
        ->and($charge->base_amount_cents)->toBe(71940)
        ->and($charge->amount_cents)->toBe(71940 + 23880);
});

test('the contracted price is frozen: a later plan increase does not change the renewal', function () {
    app(SubscriptionService::class)->choosePaymentMethod($this->subscription, PaymentMethod::Pix, $this->owner);
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    payCharge($charge);

    Plan::main()->forceFill(['price_cents' => 15990])->save();
    $this->travelTo($charge->period_end->copy()->subDays(3));
    app(BillingCycle::class)->run();

    $renewal = BillingCharge::query()->where('id', '!=', $charge->id)->sole();

    expect($renewal->amount_cents)->toBe(13990)
        ->and($this->subscription->fresh()->price_cents)->toBe(13990);
});

test('a promotion valid at contracting becomes the contract discount until its end date', function () {
    Plan::main()->forceFill(['promotional_discount_percent' => 10, 'promotional_until' => now()->addMonth()])->save();

    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);

    expect($charge->discount_cents)->toBe(1399)
        ->and($charge->amount_cents)->toBe(13990 - 1399)
        ->and($this->subscription->fresh()->discount_percent)->toBe(10);
});

test('during the trial without charges the cycle changes at once', function () {
    $this->actingAs($this->owner)->post('/assinatura/ciclo', ['interval' => 'annual'])->assertRedirect('/assinatura');

    $subscription = $this->subscription->fresh();

    expect($subscription->billing_interval)->toBe(BillingInterval::Annual)
        ->and($subscription->scheduled_interval)->toBeNull()
        ->and($subscription->events()->where('type', 'interval_changed')->exists())->toBeTrue();
});

test('the cycle cannot change while a charge is open, so two charges never overlap', function () {
    app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);

    $this->actingAs($this->owner)->post('/assinatura/ciclo', ['interval' => 'annual'])
        ->assertSessionHasErrors(['interval' => 'Há uma cobrança em aberto. Pague-a ou aguarde o vencimento antes de trocar o ciclo.']);

    expect($this->subscription->fresh()->billing_interval)->toBe(BillingInterval::Monthly);
});

test('with a paid period running the change is scheduled and applies to the next charge, without proportional charge', function () {
    app(SubscriptionService::class)->choosePaymentMethod($this->subscription, PaymentMethod::Pix, $this->owner);
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    payCharge($charge);

    $this->actingAs($this->owner)->post('/assinatura/ciclo', ['interval' => 'semiannual']);

    expect($this->subscription->fresh()->scheduled_interval)->toBe(BillingInterval::Semiannual)
        ->and(BillingCharge::query()->count())->toBe(1);

    $this->travelTo($charge->period_end->copy()->subDays(3));
    app(BillingCycle::class)->run();

    $renewal = BillingCharge::query()->where('id', '!=', $charge->id)->sole();
    $subscription = $this->subscription->fresh();

    expect($renewal->billing_interval)->toBe(BillingInterval::Semiannual)
        ->and($renewal->amount_cents)->toBe(71940)
        ->and($renewal->period_start->equalTo($charge->period_end))->toBeTrue()
        ->and($subscription->billing_interval)->toBe(BillingInterval::Semiannual)
        ->and($subscription->scheduled_interval)->toBeNull();
});

test('only the owner changes the cycle', function () {
    $operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $operator->id]);

    $this->actingAs($operator)->post('/assinatura/ciclo', ['interval' => 'annual'])->assertForbidden();

    expect($this->subscription->fresh()->billing_interval)->toBe(BillingInterval::Monthly);
});

test('no charge is issued in production while the prices are not approved', function () {
    BillingSetting::current()->forceFill(['efi_environment' => 'production'])->save();

    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix)->fresh();

    expect($charge->status)->toBe(ChargeStatus::Pending)
        ->and($charge->issue_attempts)->toBe(0)
        ->and($charge->last_error)->toContain('não aprovados');
    Http::assertNothingSent();
});

test('the RootAdmin changes the prices with an audit of previous and new values, and a price change withdraws the approval', function () {
    $root = User::factory()->rootAdmin()->create();
    Plan::main()->forceFill(['prices_approved_at' => now()])->save();

    $this->actingAs($root)->patch('/rootadmin/financeiro/plano', [
        'name' => 'VetorSmart Completo', 'price' => '149.90', 'semiannual_price' => '719.40', 'annual_price' => '1318.80',
        'trial_days' => 14, 'included_users' => 3, 'extra_user_price' => '19.90', 'promotional_discount_percent' => 0, 'prices_approved' => true,
    ])->assertRedirect();

    $plan = Plan::main();
    $audit = AuditLog::query()->where('action', 'billing.plan_updated')->sole();

    expect($plan->price_cents)->toBe(14990)
        ->and($plan->prices_approved_at)->toBeNull()
        ->and($audit->metadata['changes']['price_cents'])->toBe(['de' => 13990, 'para' => 14990]);
});

test('the promotional discount is limited to 90 percent', function () {
    $root = User::factory()->rootAdmin()->create();

    $this->actingAs($root)->patch('/rootadmin/financeiro/plano', ['name' => 'VetorSmart Completo', 'trial_days' => 14, 'promotional_discount_percent' => 95])
        ->assertSessionHasErrors(['promotional_discount_percent' => 'O desconto promocional vai até 90%.']);
});

test('with a charge open, Minha Assinatura shows its fixed amount as the next charge, not a new quote', function () {
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 1]);

    expect($charge->fresh()->amount_cents)->toBe(13990);

    $this->actingAs($this->owner)->get('/assinatura')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('subscription.next_charge.total', 'R$ 139,90')
            ->where('subscription.next_charge.open', true));
});
