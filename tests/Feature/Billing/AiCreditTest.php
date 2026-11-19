<?php

use App\Enums\AiCreditPurchaseStatus;
use App\Enums\BillingSource;
use App\Models\AiCreditAccount;
use App\Models\AiCreditEntry;
use App\Models\AiCreditPackage;
use App\Models\AiCreditPurchase;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\AiCreditPurchaseService;
use App\Services\Billing\AiCreditService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

/**
 * AI credits with a franchise of 100 credits per month and 1 000 tokens per credit. No real purchase is made: a
 * purchase waits for payment and the RootAdmin confirms it.
 */
beforeEach(function () {
    enableBillingEnforcement();
    Notification::fake();
    Plan::main()->forceFill(['ai_monthly_credits' => 100])->save();
    BillingSetting::current()->forceFill(['ai_tokens_per_credit' => 1000, 'ai_low_balance_percent' => 20])->save();

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    app(SubscriptionService::class)->startTrial($this->tenant, BillingSource::System);

    $this->credits = app(AiCreditService::class);
    $this->root = User::factory()->rootAdmin()->create();
    $this->package = AiCreditPackage::query()->create(['name' => 'Pacote 500', 'credits' => 500, 'price_cents' => 4990, 'active' => true]);
});

function consume(Tenant $tenant, string $reference, int $tokens): AiCreditEntry
{
    return app(AiCreditService::class)->recordConsumption($tenant, $reference, 'classify_service', 'fake', $tokens);
}

function paidPurchase(Tenant $tenant, User $owner, User $root): AiCreditPurchase
{
    BillingSetting::current()->forceFill(['ai_credit_purchases_enabled' => true])->save();
    $purchase = app(AiCreditPurchaseService::class)->request($tenant, AiCreditPackage::query()->firstOrFail(), $owner);

    return app(AiCreditPurchaseService::class)->confirmPayment($purchase, $root, 'Pix de homologação conferido');
}

test('the franchise is granted once a month and the leftover of the previous month expires', function () {
    expect($this->credits->account($this->tenant)->included_balance)->toBe(100);

    consume($this->tenant, 'a', 30_000);
    $this->credits->account($this->tenant);

    expect($this->credits->account($this->tenant)->included_balance)->toBe(70)
        ->and(AiCreditEntry::query()->where('kind', 'monthly_grant')->count())->toBe(1);

    $this->travelTo(now($this->tenant->timezone)->addMonthNoOverflow()->startOfMonth()->addHour());

    expect($this->credits->account($this->tenant)->included_balance)->toBe(100)
        ->and(AiCreditEntry::query()->where('kind', 'included_expired')->sole()->included_delta)->toBe(-70)
        ->and(AiCreditEntry::query()->where('kind', 'monthly_grant')->count())->toBe(2);
});

test('purchased credits are used after the franchise and do not expire at the monthly renewal', function () {
    paidPurchase($this->tenant, $this->owner, $this->root);

    $entry = consume($this->tenant, 'a', 150_000);

    expect($entry->included_delta)->toBe(-100)
        ->and($entry->purchased_delta)->toBe(-50);

    $this->travelTo(now($this->tenant->timezone)->addMonthNoOverflow()->startOfMonth()->addHour());
    $account = $this->credits->account($this->tenant);

    expect($account->included_balance)->toBe(100)
        ->and($account->purchased_balance)->toBe(450);
});

test('tokens become credits rounded up, with model, operation and tokens in the history', function () {
    $entry = consume($this->tenant, 'a', 1_001);

    expect($entry->credits)->toBe(2)
        ->and($entry->model)->toBe('fake')
        ->and($entry->operation)->toBe('classify_service')
        ->and($entry->tokens)->toBe(1_001);
});

test('the same consumption recorded twice is debited once', function () {
    consume($this->tenant, 'mensagem-1', 5_000);
    consume($this->tenant, 'mensagem-1', 5_000);

    expect($this->credits->account($this->tenant)->included_balance)->toBe(95)
        ->and(AiCreditEntry::query()->where('kind', 'consumption')->count())->toBe(1);
});

test('without balance paid AI operations are blocked, and the excess of the last one is kept as uncovered', function () {
    $entry = consume($this->tenant, 'a', 120_000);

    expect($entry->uncovered_credits)->toBe(20)
        ->and($this->credits->account($this->tenant)->totalBalance())->toBe(0)
        ->and($this->credits->canSpend($this->tenant))->toBeFalse();
});

test('credits are not enforced while the plan has no franchise; consumption is still recorded', function () {
    Plan::main()->forceFill(['ai_monthly_credits' => null])->save();

    $entry = consume($this->tenant, 'a', 50_000);

    expect($this->credits->canSpend($this->tenant))->toBeTrue()
        ->and($entry->credits)->toBe(50)
        ->and($entry->included_delta + $entry->purchased_delta)->toBe(0);
});

test('the owners are warned once a month when the balance gets low and once when it runs out', function () {
    consume($this->tenant, 'a', 81_000);
    consume($this->tenant, 'b', 1_000);
    consume($this->tenant, 'c', 30_000);
    consume($this->tenant, 'd', 1_000);

    expect(TenantNotification::query()->where('user_id', $this->owner->id)->where('title', 'Saldo de créditos de IA baixo')->count())->toBe(1)
        ->and(TenantNotification::query()->where('user_id', $this->owner->id)->where('title', 'Créditos de IA esgotados')->count())->toBe(1);
});

test('the ledger reconciles with the balances, and a change outside the service is detected', function () {
    paidPurchase($this->tenant, $this->owner, $this->root);
    consume($this->tenant, 'a', 130_000);

    expect($this->credits->reconcile($this->tenant)['consistent'])->toBeTrue();

    AiCreditAccount::query()->forTenant($this->tenant)->update(['purchased_balance' => 9999]);

    expect($this->credits->reconcile($this->tenant)['consistent'])->toBeFalse();
});

test('buying credits is closed until the RootAdmin enables purchases', function () {
    $this->actingAs($this->owner)->post('/assinatura/creditos-ia', ['package_id' => $this->package->id])
        ->assertSessionHasErrors(['package' => 'A compra de créditos de IA ainda não está liberada.']);

    expect(AiCreditPurchase::query()->count())->toBe(0);
});

test('a purchase is a separate, audited record; asking again while it waits returns the same purchase', function () {
    BillingSetting::current()->forceFill(['ai_credit_purchases_enabled' => true])->save();

    $this->actingAs($this->owner)->post('/assinatura/creditos-ia', ['package_id' => $this->package->id])->assertRedirect('/assinatura');
    $this->actingAs($this->owner)->post('/assinatura/creditos-ia', ['package_id' => $this->package->id])->assertRedirect('/assinatura');

    $purchase = AiCreditPurchase::query()->sole();

    expect($purchase->status)->toBe(AiCreditPurchaseStatus::AwaitingPayment)
        ->and($purchase->credits)->toBe(500)
        ->and($purchase->price_cents)->toBe(4990)
        ->and($this->credits->account($this->tenant)->purchased_balance)->toBe(0)
        ->and(AuditLog::query()->where('action', 'ai_credits.purchase_requested')->count())->toBe(1);
});

test('confirming a payment twice credits once', function () {
    $purchase = paidPurchase($this->tenant, $this->owner, $this->root);

    $this->actingAs($this->root)->post("/rootadmin/creditos-ia/compras/{$purchase->id}/confirmar", ['reason' => 'Confirmação repetida'])->assertRedirect();

    expect($this->credits->account($this->tenant)->purchased_balance)->toBe(500)
        ->and(AiCreditEntry::query()->where('kind', 'purchase')->count())->toBe(1);
});

test('a refund removes only unused credits, for a proportional amount', function () {
    $purchase = paidPurchase($this->tenant, $this->owner, $this->root);
    consume($this->tenant, 'a', 300_000);

    $this->actingAs($this->root)->post("/rootadmin/creditos-ia/compras/{$purchase->id}/estornar", ['reason' => 'Pedido do cliente'])->assertRedirect();

    $purchase->refresh();

    expect($purchase->refunded_credits)->toBe(300)
        ->and($purchase->refunded_cents)->toBe(2994)
        ->and($purchase->status)->toBe(AiCreditPurchaseStatus::PartiallyRefunded)
        ->and($this->credits->account($this->tenant)->purchased_balance)->toBe(0)
        ->and($this->credits->reconcile($this->tenant)['consistent'])->toBeTrue();
});

test('a purchase whose credits were all used cannot be refunded', function () {
    $purchase = paidPurchase($this->tenant, $this->owner, $this->root);
    consume($this->tenant, 'a', 600_000);

    expect(fn () => app(AiCreditPurchaseService::class)->refund($purchase, $this->root, 'Pedido do cliente'))
        ->toThrow(ValidationException::class, 'Os créditos desta compra já foram consumidos');
});

test('a company sees only its own AI credit history', function () {
    consume($this->tenant, 'a', 1_000);
    $other = Tenant::factory()->create();
    $otherOwner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $other->id, 'user_id' => $otherOwner->id]);
    app(SubscriptionService::class)->startTrial($other, BillingSource::System);

    $this->actingAs($otherOwner)->get('/assinatura')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('credits.used_this_month', 0)
            ->where('credits.entries', fn ($entries) => collect($entries)->where('kind', 'consumption')->isEmpty()));
});

test('company users cannot confirm purchases or manage packages', function () {
    BillingSetting::current()->forceFill(['ai_credit_purchases_enabled' => true])->save();
    $purchase = app(AiCreditPurchaseService::class)->request($this->tenant, $this->package, $this->owner);

    $this->actingAs($this->owner)->post("/rootadmin/creditos-ia/compras/{$purchase->id}/confirmar", ['reason' => 'Tentativa indevida'])->assertForbidden();
    $this->actingAs($this->owner)->post('/rootadmin/financeiro/pacotes-ia', ['name' => 'X', 'credits' => 1, 'price' => 1])->assertForbidden();

    expect($purchase->fresh()->status)->toBe(AiCreditPurchaseStatus::AwaitingPayment);
});

test('the RootAdmin creates a package with an audit of its values', function () {
    $this->actingAs($this->root)->post('/rootadmin/financeiro/pacotes-ia', ['name' => 'Pacote 1000', 'credits' => 1000, 'price' => '89.90', 'active' => true])->assertRedirect();

    $package = AiCreditPackage::query()->where('name', 'Pacote 1000')->sole();

    expect($package->price_cents)->toBe(8990)
        ->and(AuditLog::query()->where('action', 'billing.ai_package_created')->sole()->metadata['changes']['credits'])->toBe(['de' => null, 'para' => 1000]);
});
