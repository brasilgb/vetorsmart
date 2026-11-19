<?php

use App\Enums\BillingSource;
use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Models\AiCreditPackage;
use App\Models\AiCreditPurchase;
use App\Models\BillingSetting;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\AiCreditPurchaseService;
use App\Services\Billing\ChargeService;
use App\Services\Billing\Efi\EfiChargesClient;
use App\Services\Billing\Efi\EfiPixClient;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The billing locks fail closed: a missing or invalid configuration never reaches the production gateway, never
 * issues a charge without credentials and never opens credit purchases.
 */
beforeEach(function () {
    enableBillingEnforcement();
    Notification::fake();
    Http::fake(['*' => fn (HttpRequest $request) => Http::response([
        'access_token' => 'token', 'expires_in' => 3600,
        'txid' => basename((string) parse_url($request->url(), PHP_URL_PATH)), 'status' => 'ATIVA', 'pixCopiaECola' => 'pix',
    ])]);

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->subscription = app(SubscriptionService::class)->startTrial($this->tenant, BillingSource::System);
});

test('missing billing settings are recreated in homologation with credit purchases closed', function () {
    DB::table('billing_settings')->delete();

    $settings = BillingSetting::current()->fresh();

    expect($settings->efi_environment)->toBe('homologation')
        ->and($settings->ai_credit_purchases_enabled)->toBeFalse()
        ->and(app(EfiPixClient::class)->baseUrl())->toBe(EfiPixClient::HOMOLOGATION_URL)
        ->and(app(EfiChargesClient::class)->baseUrl())->toBe(EfiChargesClient::HOMOLOGATION_URL);
});

test('an invalid environment value never points to the production gateway', function () {
    BillingSetting::current()->forceFill([
        'efi_environment' => 'PRODUCAO',
        'efi_pix_client_id' => 'id-teste', 'efi_pix_client_secret' => 'secret-teste',
        'efi_pix_certificate' => "-----BEGIN CERTIFICATE-----\nteste\n-----END CERTIFICATE-----", 'efi_pix_key' => 'chave-teste',
    ])->save();

    app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);

    Http::assertSent(fn (HttpRequest $request): bool => str_starts_with($request->url(), EfiPixClient::HOMOLOGATION_URL));
    Http::assertNotSent(fn (HttpRequest $request): bool => str_starts_with($request->url(), EfiPixClient::PRODUCTION_URL));
});

test('without credentials no request leaves and the charge stays pending with the reason', function () {
    $charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix)->fresh();

    expect($charge->status)->toBe(ChargeStatus::Pending)
        ->and($charge->last_error)->toContain('credenciais não configuradas');
    Http::assertNothingSent();
});

test('a missing purchase switch keeps credit purchases closed', function () {
    DB::table('billing_settings')->delete();
    $package = AiCreditPackage::query()->create(['name' => 'Pacote', 'credits' => 100, 'price_cents' => 1000, 'active' => true]);

    expect(fn () => app(AiCreditPurchaseService::class)->request($this->tenant, $package, $this->owner))
        ->toThrow(ValidationException::class, 'A compra de créditos de IA ainda não está liberada.');

    expect(AiCreditPurchase::query()->count())->toBe(0);
});
