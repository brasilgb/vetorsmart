<?php

use App\Enums\BillingSource;
use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\AiCreditAccount;
use App\Models\AiCreditEntry;
use App\Models\AiCreditPackage;
use App\Models\AiCreditPurchase;
use App\Models\BillingCharge;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\AiCreditPurchaseService;
use App\Services\Billing\AiCreditService;
use App\Services\Billing\ChargeService;
use App\Services\Billing\SeatService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
 * Billing concurrency against a real MySQL server (VETORSMART_CONCURRENCY=1), with the two-session pattern of
 * Operations/MysqlConcurrencyTest: session A holds a lock inside its own transaction, session B runs the real
 * service and waits at most one second for that lock.
 */

function billingSessionA(callable $callback): mixed
{
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql_first');

    try {
        return DB::transaction(fn () => $callback());
    } finally {
        DB::setDefaultConnection($previous);
    }
}

function billingSessionB(callable $callback): mixed
{
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql_second');

    try {
        return $callback();
    } finally {
        DB::setDefaultConnection($previous);
    }
}

beforeEach(function () {
    if (config('database.default') !== 'mysql' || env('VETORSMART_CONCURRENCY') !== '1') {
        $this->markTestSkipped('Concorrência: defina VETORSMART_CONCURRENCY=1 com DB_CONNECTION=mysql.');
    }

    config([
        'database.connections.mysql_first' => config('database.connections.mysql'),
        'database.connections.mysql_second' => config('database.connections.mysql'),
    ]);
    DB::purge('mysql_first');
    DB::purge('mysql_second');
    DB::connection('mysql_second')->statement('SET SESSION innodb_lock_wait_timeout = 1');
    DB::setDefaultConnection('mysql_second');

    Notification::fake();
    Http::swap(new HttpFactory);
    Http::fake(['*' => fn (HttpRequest $request) => Http::response(['access_token' => 't', 'txid' => basename((string) parse_url($request->url(), PHP_URL_PATH)), 'status' => 'CONCLUIDA', 'pixCopiaECola' => 'x', 'pix' => [['valor' => '99.00']]])]);

    $this->plan = Plan::main();
    $this->originalPrice = $this->plan->price_cents;
    $this->plan->forceFill(['price_cents' => 9900])->save();
    DB::table('billing_settings')->update([
        'efi_pix_client_id' => encrypt('id', false), 'efi_pix_client_secret' => encrypt('secret', false),
        'efi_pix_certificate' => encrypt('-----BEGIN CERTIFICATE-----x', false), 'efi_pix_key' => 'chave',
    ]);

    enableBillingEnforcement();

    $this->tenant = Tenant::factory()->create(['name' => '[CONCORRENCIA] Cobrança']);
    $this->actor = User::factory()->create();
    $this->subscription = app(SubscriptionService::class)->startTrial($this->tenant, BillingSource::System);
});

afterEach(function () {
    if (! isset($this->tenant)) {
        return;
    }

    DB::setDefaultConnection('mysql_second');

    if (isset($this->tenant)) {
        foreach (['ai_credit_entries', 'ai_credit_purchases', 'ai_credit_accounts', 'billing_charges', 'subscription_events', 'subscriptions', 'notification_deliveries', 'tenant_notifications', 'audit_logs'] as $table) {
            DB::table($table)->where('tenant_id', $this->tenant->id)->delete();
        }

        DB::table('tenants')->where('id', $this->tenant->id)->delete();
    }

    if (isset($this->actor)) {
        DB::table('users')->where('id', $this->actor->id)->delete();
    }

    if (isset($this->plan)) {
        $this->plan->forceFill(['price_cents' => $this->originalPrice, 'ai_monthly_credits' => null])->save();
    }

    DB::table('billing_settings')->update(['efi_pix_client_id' => null, 'efi_pix_client_secret' => null, 'efi_pix_certificate' => null, 'efi_pix_key' => null, 'ai_credit_purchases_enabled' => false, 'enforcement_starts_at' => null]);
    DB::table('ai_credit_packages')->where('name', '[CONCORRENCIA] Pacote')->delete();
    DB::setDefaultConnection('mysql');
    DB::purge('mysql_second');
});

test('a charge cannot be opened while another session holds the subscription, and afterwards only one exists', function () {
    billingSessionA(function () {
        Subscription::query()->whereKey($this->subscription->id)->lockForUpdate()->firstOrFail();

        expect(fn () => billingSessionB(fn () => app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix)))
            ->toThrow(QueryException::class, 'Lock wait timeout');
    });

    billingSessionB(fn () => app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix));
    billingSessionB(fn () => app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix));

    expect(BillingCharge::query()->where('subscription_id', $this->subscription->id)->count())->toBe(1);
});

test('the database refuses a second charge for the same subscription period', function () {
    $charge = billingSessionB(fn () => app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix));

    expect(fn () => DB::table('billing_charges')->insert([
        'tenant_id' => $this->tenant->id, 'subscription_id' => $this->subscription->id,
        'period_start' => $charge->period_start, 'period_end' => $charge->period_end, 'amount_cents' => 9900,
        'due_date' => $charge->due_date, 'status' => 'pending', 'method' => 'pix', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class, 'Duplicate entry');
});

test('two confirmations of the same payment at the same time settle it once', function () {
    $charge = billingSessionB(fn () => app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix));

    billingSessionA(function () use ($charge) {
        BillingCharge::query()->whereKey($charge->id)->lockForUpdate()->firstOrFail();

        expect(fn () => billingSessionB(fn () => app(ChargeService::class)->reconcile($charge)))
            ->toThrow(QueryException::class, 'Lock wait timeout');
    });

    billingSessionB(fn () => app(ChargeService::class)->reconcile($charge));
    billingSessionB(fn () => app(ChargeService::class)->reconcile($charge->fresh()));

    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid)
        ->and($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and(DB::table('subscription_events')->where('subscription_id', $this->subscription->id)->where('to_status', 'active')->where('type', 'status_changed')->count())->toBe(1);
});

test('an AI credit debit waits for the account lock, and concurrent consumptions never debit more than once each', function () {
    $this->plan->forceFill(['ai_monthly_credits' => 10])->save();
    $credits = app(AiCreditService::class);
    $account = billingSessionB(fn () => $credits->account($this->tenant));

    billingSessionA(function () use ($account, $credits) {
        AiCreditAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

        expect(fn () => billingSessionB(fn () => $credits->recordConsumption($this->tenant, 'concorrencia-1', 'classify_service', 'fake', 3000)))
            ->toThrow(QueryException::class, 'Lock wait timeout');
    });

    billingSessionB(fn () => $credits->recordConsumption($this->tenant, 'concorrencia-1', 'classify_service', 'fake', 3000));
    billingSessionB(fn () => $credits->recordConsumption($this->tenant, 'concorrencia-1', 'classify_service', 'fake', 3000));
    billingSessionB(fn () => $credits->recordConsumption($this->tenant, 'concorrencia-2', 'classify_service', 'fake', 3000));

    expect($account->fresh()->included_balance)->toBe(4)
        ->and(AiCreditEntry::query()->where('tenant_id', $this->tenant->id)->where('kind', 'consumption')->count())->toBe(2)
        ->and($credits->reconcile($this->tenant)['consistent'])->toBeTrue();
});

test('a seat change waits for the company lock, and repeating it records one change', function () {
    $actor = $this->actor;

    billingSessionA(function () use ($actor) {
        Tenant::query()->whereKey($this->tenant->id)->lockForUpdate()->firstOrFail();

        expect(fn () => billingSessionB(fn () => app(SeatService::class)->changeExtraSeats($this->subscription, 2, $actor)))
            ->toThrow(QueryException::class, 'Lock wait timeout');
    });

    billingSessionB(fn () => app(SeatService::class)->changeExtraSeats($this->subscription, 2, $actor));
    billingSessionB(fn () => app(SeatService::class)->changeExtraSeats($this->subscription, 2, $actor));

    expect($this->subscription->fresh()->extra_seats)->toBe(2)
        ->and(DB::table('subscription_events')->where('subscription_id', $this->subscription->id)->where('type', 'seats_changed')->count())->toBe(1);
});

test('two confirmations of the same credit purchase at the same time credit it once', function () {
    DB::table('billing_settings')->update(['ai_credit_purchases_enabled' => true]);
    $package = AiCreditPackage::query()->create(['name' => '[CONCORRENCIA] Pacote', 'credits' => 100, 'price_cents' => 1000, 'active' => true]);
    $purchases = app(AiCreditPurchaseService::class);
    $purchase = billingSessionB(fn () => $purchases->request($this->tenant, $package, $this->actor));

    billingSessionA(function () use ($purchase, $purchases) {
        AiCreditPurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

        expect(fn () => billingSessionB(fn () => $purchases->confirmPayment($purchase, $this->actor, 'Confirmação concorrente')))
            ->toThrow(QueryException::class, 'Lock wait timeout');
    });

    billingSessionB(fn () => $purchases->confirmPayment($purchase, $this->actor, 'Confirmação 1'));
    billingSessionB(fn () => $purchases->confirmPayment($purchase, $this->actor, 'Confirmação 2'));

    expect(AiCreditEntry::query()->where('tenant_id', $this->tenant->id)->where('kind', 'purchase')->count())->toBe(1)
        ->and(app(AiCreditService::class)->account($this->tenant)->purchased_balance)->toBe(100);
});
