<?php

use App\Enums\BillingSource;
use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\BillingCharge;
use App\Models\BillingSetting;
use App\Models\BillingWebhookEvent;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\BillingCycle;
use App\Services\Billing\ChargeService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * Efí is never called for real: every request goes to Http::fake, with homologation addresses and fake credentials.
 */
beforeEach(function () {
    Notification::fake();
    Plan::main()->forceFill(['price_cents' => 14990])->save();

    BillingSetting::current()->forceFill([
        'efi_pix_client_id' => 'client-id-teste',
        'efi_pix_client_secret' => 'client-secret-teste',
        'efi_pix_certificate' => "-----BEGIN CERTIFICATE-----\nteste\n-----END CERTIFICATE-----",
        'efi_pix_key' => 'chave-pix-teste',
        'efi_charges_client_id' => 'charges-id-teste',
        'efi_charges_client_secret' => 'charges-secret-teste',
        'webhook_secret' => 'segredo-webhook-teste',
    ])->save();

    $this->tenant = Tenant::factory()->create(['document' => '12345678000199']);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->subscription = app(SubscriptionService::class)->startTrial($this->tenant, BillingSource::System);
});

/**
 * Efí Pix in homologation: token, charge creation and charge query with the given status.
 */
function fakePix(string $status = 'ATIVA', string $paid = '149.90'): void
{
    // A fresh client factory, so a new answer replaces the previous one instead of being queued behind it.
    Http::swap(new HttpFactory);
    Http::fake([
        'pix-h.api.efipay.com.br/oauth/token' => Http::response(['access_token' => 'token-pix', 'expires_in' => 3600]),
        'pix-h.api.efipay.com.br/v2/cob/*' => function (HttpRequest $request) use ($status, $paid) {
            $txid = basename(parse_url($request->url(), PHP_URL_PATH));

            return Http::response([
                'txid' => $txid,
                'status' => $request->method() === 'PUT' ? 'ATIVA' : $status,
                'pixCopiaECola' => '00020101021226830014BR.GOV.BCB.PIX'.$txid,
                'pix' => $status === 'CONCLUIDA' ? [['endToEndId' => 'E123', 'valor' => $paid]] : [],
            ]);
        },
    ]);
}

function pixWebhook(string $txid, string $endToEnd = 'E123'): array
{
    return ['pix' => [['endToEndId' => $endToEnd, 'txid' => $txid, 'valor' => '149.90', 'horario' => now()->toIso8601String()]]];
}

test('contracting with Pix issues one charge for the period after the trial, at the homologation address', function () {
    fakePix();

    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'pix'])->assertRedirect('/assinatura');

    $charge = BillingCharge::query()->sole();

    expect($charge->status)->toBe(ChargeStatus::Waiting)
        ->and($charge->amount_cents)->toBe(14990)
        ->and($charge->gateway_id)->toBe($charge->pixTxid())
        ->and(strlen($charge->gateway_id))->toBeGreaterThanOrEqual(26)->toBeLessThanOrEqual(35)
        ->and($charge->pix_copy_paste)->toContain('BR.GOV.BCB.PIX')
        ->and($charge->period_start->equalTo($this->subscription->trial_ends_at))->toBeTrue();

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'PUT'
        && str_starts_with($request->url(), 'https://pix-h.api.efipay.com.br/v2/cob/')
        && $request['valor']['original'] === '149.90');
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'pix.api.efipay.com.br'));
});

test('asking again for the charge of the same period returns the same charge and calls Efí once', function () {
    fakePix();

    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'pix']);
    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'pix']);
    app(ChargeService::class)->openFor($this->subscription->fresh(), PaymentMethod::Pix);

    expect(BillingCharge::query()->count())->toBe(1);
    Http::assertSentCount(2);
});

test('contracting is closed while the plan has no approved price', function () {
    Plan::main()->forceFill(['price_cents' => null])->save();
    Http::fake();

    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'pix'])->assertSessionHasErrors('plan');

    expect(BillingCharge::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('the billet requires the company document and sends it to Efí Cobranças', function () {
    $this->tenant->forceFill(['document' => null])->save();
    Http::fake([
        'cobrancas-h.api.efipay.com.br/v1/authorize' => Http::response(['access_token' => 'token-charges', 'expires_in' => 600]),
        'cobrancas-h.api.efipay.com.br/v1/charge/one-step' => Http::response(['data' => ['charge_id' => 987654, 'link' => 'https://visualizacao.gerencianet.com.br/emissao/x', 'pdf' => ['charge' => 'https://download.gerencianet.com.br/x.pdf'], 'barcode' => '0000']]),
    ]);

    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'boleto'])->assertSessionHasErrors('document');
    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'boleto', 'document' => '12.345.678/0001-99'])->assertRedirect('/assinatura');

    $charge = BillingCharge::query()->sole();

    expect($charge->gateway_id)->toBe('987654')
        ->and($charge->billet_pdf)->toBe('https://download.gerencianet.com.br/x.pdf')
        ->and($this->tenant->fresh()->document)->toBe('12345678000199');
    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/v1/charge/one-step')
        && $request['payment']['banking_billet']['customer']['juridical_person']['cnpj'] === '12345678000199'
        && $request['items'][0]['value'] === 14990
        && str_contains($request['metadata']['notification_url'], '/api/webhooks/efi/segredo-webhook-teste/cobrancas'));
});

test('an Efí outage keeps the charge pending with the error, and the daily routine issues it later', function () {
    Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);

    $this->actingAs($this->owner)->post('/assinatura/contratar', ['method' => 'pix'])->assertRedirect();

    $charge = BillingCharge::query()->sole();
    expect($charge->status)->toBe(ChargeStatus::Pending)
        ->and($charge->issue_attempts)->toBe(1)
        ->and($charge->last_error)->toContain('HTTP 503')
        ->and($charge->last_error)->not->toContain('client-secret-teste');

    fakePix();
    app(BillingCycle::class)->run();

    expect($charge->fresh()->status)->toBe(ChargeStatus::Waiting)
        ->and($charge->fresh()->last_error)->toBeNull();
});

test('a confirmed Pix notice settles the charge and activates the subscription for the paid period', function () {
    fakePix();
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    fakePix('CONCLUIDA');

    $this->postJson('/api/webhooks/efi/segredo-webhook-teste/pix', pixWebhook($charge->gateway_id))->assertOk()->assertJson(['processed' => 1]);

    $subscription = $this->subscription->fresh();
    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid)
        ->and($charge->fresh()->paid_amount_cents)->toBe(14990)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_end->equalTo($charge->period_end))->toBeTrue();
});

test('a notice with a wrong secret is refused before anything is read', function () {
    Http::fake();

    $this->postJson('/api/webhooks/efi/segredo-errado/pix', pixWebhook('VSMART123'))->assertUnauthorized();
    $this->postJson('/api/webhooks/efi/segredo-errado/cobrancas', ['notification' => 'abc'])->assertUnauthorized();

    expect(BillingWebhookEvent::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('a notice that Efí does not confirm (forged or premature) settles nothing', function () {
    fakePix();
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);

    $this->postJson('/api/webhooks/efi/segredo-webhook-teste/pix', pixWebhook($charge->gateway_id))->assertOk();

    expect($charge->fresh()->status)->toBe(ChargeStatus::Waiting)
        ->and($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Trialing);
});

test('the same notice delivered twice is processed once', function () {
    fakePix();
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    fakePix('CONCLUIDA');

    $this->postJson('/api/webhooks/efi/segredo-webhook-teste/pix', pixWebhook($charge->gateway_id))->assertJson(['processed' => 1]);
    $this->postJson('/api/webhooks/efi/segredo-webhook-teste/pix', pixWebhook($charge->gateway_id))->assertJson(['processed' => 0, 'duplicated' => 1]);

    expect(BillingWebhookEvent::query()->count())->toBe(1)
        ->and($this->subscription->events()->where('type', 'status_changed')->where('to_status', 'active')->count())->toBe(1);
});

test('a payment below the charged amount does not settle it', function () {
    fakePix();
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    fakePix('CONCLUIDA', '10.00');

    $this->postJson('/api/webhooks/efi/segredo-webhook-teste/pix', pixWebhook($charge->gateway_id));

    expect($charge->fresh()->status)->toBe(ChargeStatus::Waiting)
        ->and($charge->fresh()->last_error)->toContain('menor que o cobrado');
});

test('a late notice after the payment never reverts a paid charge', function () {
    fakePix();
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    fakePix('CONCLUIDA');
    app(ChargeService::class)->reconcile($charge);

    fakePix('REMOVIDA_PELO_PSP');
    $this->postJson('/api/webhooks/efi/segredo-webhook-teste/pix', pixWebhook($charge->gateway_id, 'E999'))->assertOk();

    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid);
});

test('a Cobranças notice is read at Efí and settles the billet once per event', function () {
    $charge = BillingCharge::query()->forceCreate([
        'tenant_id' => $this->tenant->id, 'subscription_id' => $this->subscription->id,
        'period_start' => $this->subscription->trial_ends_at, 'period_end' => $this->subscription->trial_ends_at->addMonth(),
        'amount_cents' => 14990, 'due_date' => $this->subscription->trial_ends_at, 'status' => ChargeStatus::Waiting, 'method' => PaymentMethod::Billet, 'gateway_id' => '555',
    ]);
    Http::fake([
        'cobrancas-h.api.efipay.com.br/v1/authorize' => Http::response(['access_token' => 'token-charges']),
        'cobrancas-h.api.efipay.com.br/v1/notification/*' => Http::response(['data' => [
            ['id' => 1, 'type' => 'charge', 'identifiers' => ['charge_id' => 555], 'status' => ['previous' => null, 'current' => 'waiting']],
            ['id' => 2, 'type' => 'charge', 'identifiers' => ['charge_id' => 555], 'status' => ['previous' => 'waiting', 'current' => 'paid']],
        ]]),
        'cobrancas-h.api.efipay.com.br/v1/charge/555' => Http::response(['data' => ['charge_id' => 555, 'status' => 'paid', 'paid_value' => 14990, 'total' => 14990]]),
    ]);

    $this->post('/api/webhooks/efi/segredo-webhook-teste/cobrancas', ['notification' => 'token-abc'])->assertOk();
    $this->post('/api/webhooks/efi/segredo-webhook-teste/cobrancas', ['notification' => 'token-abc'])->assertOk()->assertJson(['processed' => 0, 'duplicated' => 2]);

    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid)
        ->and(BillingWebhookEvent::query()->count())->toBe(2);
});

test('a Cobranças notice that cannot be read now is answered 503, so Efí sends it again', function () {
    Http::fake(['*' => Http::response([], 500)]);

    $this->post('/api/webhooks/efi/segredo-webhook-teste/cobrancas', ['notification' => 'token-abc'])->assertStatus(503);
});

test('paying the overdue charge after the financial suspension reactivates the company for the unpaid period, grace included', function () {
    fakePix();
    $this->travel(15)->days();
    app(BillingCycle::class)->run();
    $this->travel(6)->days();
    app(BillingCycle::class)->run();
    expect($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Suspended);

    $charge = app(ChargeService::class)->openFor($this->subscription->fresh(), PaymentMethod::Pix);
    fakePix('CONCLUIDA');
    $this->postJson('/api/webhooks/efi/segredo-webhook-teste/pix', pixWebhook($charge->gateway_id));

    $subscription = $this->subscription->fresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_end->isFuture())->toBeTrue()
        ->and($subscription->current_period_start->equalTo($charge->period_start))->toBeTrue()
        ->and($subscription->current_period_end->equalTo($charge->period_end))->toBeTrue();
    $this->actingAs($this->owner)->get('/clientes')->assertOk();
});

test('the renewal charge is opened before the paid period ends, once', function () {
    fakePix();
    app(SubscriptionService::class)->choosePaymentMethod($this->subscription, PaymentMethod::Pix, $this->owner);
    $first = app(ChargeService::class)->openFor($this->subscription->fresh(), PaymentMethod::Pix);
    fakePix('CONCLUIDA');
    app(ChargeService::class)->reconcile($first);

    $this->travel(14 + 25)->days();
    fakePix();
    app(BillingCycle::class)->run();
    app(BillingCycle::class)->run();

    expect(BillingCharge::query()->count())->toBe(2)
        ->and(BillingCharge::query()->latest('id')->first()->period_start->equalTo($first->period_end))->toBeTrue();
});

test('the RootAdmin settles a charge manually with a reason, and it is audited', function () {
    fakePix();
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    $root = User::factory()->rootAdmin()->create();

    $this->actingAs($root)->post("/rootadmin/cobrancas/{$charge->id}/baixa-manual", [])->assertSessionHasErrors('reason');
    $this->actingAs($root)->post("/rootadmin/cobrancas/{$charge->id}/baixa-manual", ['reason' => 'Transferência recebida'])->assertRedirect();

    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid)
        ->and($charge->fresh()->paid_via)->toBe('manual')
        ->and(AuditLog::query()->where('action', 'billing.charge_settled_manually')->exists())->toBeTrue();
});

test('company users cannot reach the RootAdmin billing actions', function () {
    fakePix();
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);

    $this->actingAs($this->owner)->get('/rootadmin/assinaturas')->assertForbidden();
    $this->actingAs($this->owner)->post("/rootadmin/cobrancas/{$charge->id}/baixa-manual", ['reason' => 'Tentativa indevida'])->assertForbidden();
    $this->actingAs($this->owner)->patch('/rootadmin/financeiro/plano', ['name' => 'X', 'trial_days' => 1, 'promotional_discount_percent' => 0])->assertForbidden();

    expect($charge->fresh()->status)->toBe(ChargeStatus::Waiting);
});

test('a company sees only its own charges', function () {
    fakePix();
    app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    $other = Tenant::factory()->create();
    $otherOwner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $other->id, 'user_id' => $otherOwner->id]);
    app(SubscriptionService::class)->startTrial($other, BillingSource::System);

    $this->actingAs($otherOwner)->get('/assinatura')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('charges.data', 0));
});

test('the settings screen never sends a credential back and keeps a secret when the field is left empty', function () {
    $root = User::factory()->rootAdmin()->create();

    $response = $this->actingAs($root)->get('/rootadmin/financeiro/configuracoes')->assertOk();
    $content = $response->getContent();

    expect($content)->not->toContain('client-secret-teste')
        ->and($content)->not->toContain('BEGIN CERTIFICATE')
        ->and($content)->not->toContain('charges-secret-teste');

    $this->actingAs($root)->patch('/rootadmin/financeiro/efi', [
        'efi_environment' => 'homologation', 'efi_pix_client_secret' => '', 'grace_days' => 3, 'renewal_days_before' => 7, 'billet_due_days' => 5, 'ai_tokens_per_credit' => 1000, 'ai_low_balance_percent' => 20,
    ])->assertRedirect();

    expect(BillingSetting::current()->efi_pix_client_secret)->toBe('client-secret-teste')
        ->and(BillingSetting::current()->grace_days)->toBe(3)
        ->and(json_encode(AuditLog::query()->latest('id')->first()->metadata))->not->toContain('secret-teste');
});

test('switching to production requires an explicit confirmation', function () {
    $root = User::factory()->rootAdmin()->create();

    $this->actingAs($root)->patch('/rootadmin/financeiro/efi', ['efi_environment' => 'production', 'grace_days' => 5, 'renewal_days_before' => 7, 'billet_due_days' => 5])
        ->assertSessionHasErrors('confirm_production');

    expect(BillingSetting::current()->efi_environment)->toBe('homologation');
});

test('credentials are stored encrypted', function () {
    $raw = DB::table('billing_settings')->value('efi_pix_client_secret');

    expect($raw)->not->toBe('client-secret-teste')
        ->and($raw)->not->toContain('client-secret-teste');
});

test('a late payment for a period that already ended buys a full period from the payment date', function () {
    fakePix();
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);
    $this->subscription->forceFill(['status' => SubscriptionStatus::Suspended])->save();

    $this->travel(80)->days();
    fakePix('CONCLUIDA');
    app(ChargeService::class)->reconcile($charge);

    $subscription = $this->subscription->fresh();
    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_start->isSameDay(now()))->toBeTrue()
        ->and($subscription->current_period_end->isSameDay(now()->addMonth()))->toBeTrue();
});
