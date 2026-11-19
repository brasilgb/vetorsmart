<?php

use App\Enums\BillingSource;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\AiCreditPackage;
use App\Models\AuditLog;
use App\Models\BillingCharge;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Segment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\AiCreditPurchaseService;
use App\Services\Billing\BillingCycle;
use App\Services\Billing\BillingEnforcement;
use App\Services\Billing\ChargeService;
use App\Services\Billing\SeatService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

/**
 * Billing enforcement is off unless explicitly enabled and started. While off, subscriptions are history only: no
 * suspension, no charge, no paid seat, no credit purchase, and the panel stays open.
 */
beforeEach(function () {
    Notification::fake();
    Http::fake();

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->subscription = app(SubscriptionService::class)->startTrial($this->tenant, BillingSource::System);
});

test('enforcement is on only with the switch at true and a start date reached', function (mixed $switch, ?string $startsAt, bool $active) {
    config(['platform.billing_enforcement_enabled' => $switch]);
    BillingSetting::current()->forceFill(['enforcement_starts_at' => $startsAt === null ? null : now()->modify($startsAt)])->save();

    expect(app(BillingEnforcement::class)->active())->toBe($active);
})->with([
    'ligada e iniciada' => [true, '-1 day', true],
    'ligada, sem data' => [true, null, false],
    'ligada, data futura' => [true, '+1 day', false],
    'desligada com data' => [false, '-1 day', false],
    'texto "true" não liga' => ['true', '-1 day', false],
]);

test('only the literal true in the environment enables the switch', function (string $value, bool $expected) {
    putenv("PLATFORM_BILLING_ENFORCEMENT_ENABLED={$value}");

    try {
        expect((require config_path('platform.php'))['billing_enforcement_enabled'])->toBe($expected);
    } finally {
        putenv('PLATFORM_BILLING_ENFORCEMENT_ENABLED');
    }
})->with([
    'true' => ['true', true],
    'false' => ['false', false],
    'yes' => ['yes', false],
    '1' => ['1', false],
    'vazio' => ['', false],
]);

test('while off, the end of the trial and of the grace never suspends, and nobody is warned', function () {
    $this->travel(40)->days();
    $report = app(BillingCycle::class)->run();

    expect($report['enforcement'])->toBe(0)
        ->and($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Trialing)
        ->and(BillingCharge::query()->count())->toBe(0)
        ->and(TenantNotification::query()->count())->toBe(0);
    $this->actingAs($this->owner)->get('/clientes')->assertOk();
});

test('while off, a company is never closed, even with a suspended subscription', function () {
    Subscription::query()->whereKey($this->subscription->id)->update(['status' => SubscriptionStatus::Suspended->value]);

    $this->actingAs($this->owner)->get('/clientes')->assertOk();
});

test('a public registration still records the trial while billing is off', function () {
    $segment = Segment::factory()->create();

    $this->post(route('register.store'), [
        'company_name' => 'Empresa Nova', 'segment_id' => $segment->id, 'name' => 'Dona', 'email' => 'dona@example.com',
        'password' => 'Senha-Forte-2026', 'password_confirmation' => 'Senha-Forte-2026',
    ])->assertRedirect();

    expect(Subscription::query()->where('tenant_id', '!=', $this->tenant->id)->sole()->status)->toBe(SubscriptionStatus::Trialing);
});

test('while off, no charge is opened, no paid seat is granted and no credit purchase is accepted', function () {
    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'pix'])
        ->assertSessionHasErrors(['plan' => BillingEnforcement::inactiveMessage()]);
    $this->actingAs($this->owner)->post('/assinatura/vagas', ['extra_seats' => 2])
        ->assertSessionHasErrors(['extra_seats' => 'A contratação de vagas adicionais será liberada quando a cobrança entrar em vigor.']);

    BillingSetting::current()->forceFill(['ai_credit_purchases_enabled' => true])->save();
    $package = AiCreditPackage::query()->create(['name' => 'Pacote', 'credits' => 100, 'price_cents' => 1000, 'active' => true]);

    expect(fn () => app(AiCreditPurchaseService::class)->request($this->tenant, $package, $this->owner))->toThrow(ValidationException::class);
    expect(BillingCharge::query()->count())->toBe(0)
        ->and($this->subscription->fresh()->extra_seats)->toBe(0);
    Http::assertNothingSent();
});

test('while off, a pending charge left from before is never issued', function () {
    enableBillingEnforcement();
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    config(['platform.billing_enforcement_enabled' => false]);
    Http::fake();

    app(ChargeService::class)->issue($charge->fresh());

    expect($charge->fresh()->last_error)->toBe('Cobrança fora de vigor: nenhuma emissão.');
    Http::assertNothingSent();
});

test('Minha Assinatura tells that billing is not in force and offers no contracting', function () {
    $this->actingAs($this->owner)->get('/assinatura')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('billingEnforced', false)
            ->where('subscription.seats_change_mode', 'not_in_force'));
});

test('with billing in force the approved lifecycle applies again', function () {
    enableBillingEnforcement();

    $this->travel(15)->days();
    app(BillingCycle::class)->run();

    expect($this->subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue);
});

/**
 * Prerequisites for the transition command: switch on, approved prices, Efí in production with full credentials.
 */
function meetEnforcementPrerequisites(): void
{
    config(['platform.billing_enforcement_enabled' => true]);
    Plan::main()->forceFill(['prices_approved_at' => now()])->save();
    BillingSetting::current()->forceFill([
        'efi_environment' => 'production',
        'efi_pix_client_id' => 'id-teste', 'efi_pix_client_secret' => 'secret-teste',
        'efi_pix_certificate' => "-----BEGIN CERTIFICATE-----\nteste\n-----END CERTIFICATE-----", 'efi_pix_key' => 'chave-teste',
        'efi_charges_client_id' => 'charges-id', 'efi_charges_client_secret' => 'charges-secret',
    ])->save();
}

test('the transition refuses to run with unmet prerequisites and changes nothing', function () {
    $this->artisan('billing:start-enforcement', ['--starts-at' => now()->addMonth()->toDateString(), '--execute' => true])
        ->expectsOutputToContain('Pré-requisitos não atendidos')
        ->assertFailed();

    expect(BillingSetting::current()->enforcement_starts_at)->toBeNull();
});

test('the transition preview changes nothing', function () {
    meetEnforcementPrerequisites();

    $this->artisan('billing:start-enforcement', ['--starts-at' => now()->addMonth()->toDateString()])
        ->expectsOutputToContain('Prévia apenas')
        ->assertSuccessful();

    expect(BillingSetting::current()->enforcement_starts_at)->toBeNull();
});

test('the transition extends trials to the first due date, without retroactive suspension, and is audited', function () {
    meetEnforcementPrerequisites();
    $legacy = Tenant::factory()->create();
    $this->travel(30)->days();
    $startsAt = now()->addDays(10)->startOfDay();

    $this->artisan('billing:start-enforcement', ['--starts-at' => $startsAt->toDateString(), '--adaptation-days' => 5, '--include-legacy' => true, '--execute' => true])
        ->expectsConfirmation('A homologação da Efí foi concluída e aprovada?', 'yes')
        ->expectsQuestion('Digite exatamente "INICIAR COBRANCA" para confirmar', 'INICIAR COBRANCA')
        ->assertSuccessful();

    $firstDue = $startsAt->copy()->addDays(5);
    expect($this->subscription->fresh()->trial_ends_at->equalTo($firstDue))->toBeTrue()
        ->and(Subscription::query()->where('tenant_id', $legacy->id)->sole()->trial_ends_at->equalTo($firstDue))->toBeTrue()
        ->and(BillingSetting::current()->enforcement_starts_at->equalTo($startsAt))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'billing.enforcement_scheduled')->sole()->metadata['trials_extended'])->toBe(1);

    $this->travelTo($startsAt->copy()->addDay());
    app(BillingCycle::class)->run();
    expect($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Trialing);
});

test('the transition without a typed confirmation changes nothing', function () {
    meetEnforcementPrerequisites();

    $this->artisan('billing:start-enforcement', ['--starts-at' => now()->addMonth()->toDateString(), '--execute' => true])
        ->expectsConfirmation('A homologação da Efí foi concluída e aprovada?', 'yes')
        ->expectsQuestion('Digite exatamente "INICIAR COBRANCA" para confirmar', 'sim')
        ->assertFailed();

    expect(BillingSetting::current()->enforcement_starts_at)->toBeNull();
});

test('the seat service refuses changes while billing is off', function () {
    expect(fn () => app(SeatService::class)->changeExtraSeats($this->subscription, 1, $this->owner))
        ->toThrow(ValidationException::class, 'A contratação de vagas adicionais será liberada quando a cobrança entrar em vigor.');
});
