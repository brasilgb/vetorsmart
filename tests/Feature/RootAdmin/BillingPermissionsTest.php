<?php

use App\Enums\BillingSource;
use App\Enums\PaymentMethod;
use App\Models\AiCreditPackage;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\AiCreditPurchaseService;
use App\Services\Billing\ChargeService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Every financial operation of the RootAdmin is closed to visitors and to company users, whatever their role, and
 * changes nothing when refused.
 */
beforeEach(function () {
    enableBillingEnforcement();
    Notification::fake();
    Http::fake();

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->subscription = app(SubscriptionService::class)->startTrial($this->tenant, BillingSource::System);
    $this->charge = app(ChargeService::class)->openFor($this->subscription, PaymentMethod::Pix);

    BillingSetting::current()->forceFill(['ai_credit_purchases_enabled' => true])->save();
    $this->package = AiCreditPackage::query()->create(['name' => 'Pacote', 'credits' => 100, 'price_cents' => 1000, 'active' => true]);
    $this->purchase = app(AiCreditPurchaseService::class)->request($this->tenant, $this->package, $this->owner);
});

/**
 * @return array<string, array{0: string, 1: string}>
 */
function rootAdminBillingRoutes(): array
{
    return [
        'lista de assinaturas' => ['get', '/rootadmin/assinaturas'],
        'detalhe da assinatura' => ['get', '/rootadmin/assinaturas/{subscription}'],
        'iniciar teste' => ['post', '/rootadmin/tenants/{tenant}/assinatura'],
        'prorrogar teste' => ['post', '/rootadmin/assinaturas/{subscription}/prorrogar'],
        'condições' => ['patch', '/rootadmin/assinaturas/{subscription}/condicoes'],
        'cancelar assinatura' => ['post', '/rootadmin/assinaturas/{subscription}/cancelar'],
        'baixa manual' => ['post', '/rootadmin/cobrancas/{charge}/baixa-manual'],
        'sincronizar cobrança' => ['post', '/rootadmin/cobrancas/{charge}/sincronizar'],
        'tela plano e Efí' => ['get', '/rootadmin/financeiro/configuracoes'],
        'salvar plano' => ['patch', '/rootadmin/financeiro/plano'],
        'salvar Efí' => ['patch', '/rootadmin/financeiro/efi'],
        'testar Efí' => ['post', '/rootadmin/financeiro/efi/testar'],
        'criar pacote' => ['post', '/rootadmin/financeiro/pacotes-ia'],
        'editar pacote' => ['patch', '/rootadmin/financeiro/pacotes-ia/{package}'],
        'confirmar compra' => ['post', '/rootadmin/creditos-ia/compras/{purchase}/confirmar'],
        'cancelar compra' => ['post', '/rootadmin/creditos-ia/compras/{purchase}/cancelar'],
        'estornar compra' => ['post', '/rootadmin/creditos-ia/compras/{purchase}/estornar'],
    ];
}

function rootAdminUrl(string $path): string
{
    return strtr($path, [
        '{subscription}' => (string) test()->subscription->id,
        '{tenant}' => (string) test()->tenant->id,
        '{charge}' => (string) test()->charge->id,
        '{package}' => (string) test()->package->id,
        '{purchase}' => (string) test()->purchase->id,
    ]);
}

test('a visitor is sent to the login', function (string $method, string $path) {
    $this->{$method}(rootAdminUrl($path), ['reason' => 'Tentativa indevida'])->assertRedirect('/login');
})->with(rootAdminBillingRoutes());

test('a company owner is refused and nothing changes', function (string $method, string $path) {
    $this->actingAs($this->owner)->{$method}(rootAdminUrl($path), ['reason' => 'Tentativa indevida', 'name' => 'X', 'price' => 1, 'credits' => 1, 'days' => 30, 'trial_days' => 1, 'promotional_discount_percent' => 0])
        ->assertForbidden();

    expect($this->charge->fresh()->status)->toBe($this->charge->status)
        ->and($this->purchase->fresh()->status->value)->toBe('awaiting_payment')
        ->and(AuditLog::query()->where('action', 'like', 'billing.%')->exists())->toBeFalse();
})->with(rootAdminBillingRoutes());

test('approving and revoking the commercial table are audited with the previous and new approval', function () {
    $root = User::factory()->rootAdmin()->create();
    $payload = ['name' => 'VetorSmart Completo', 'price' => '139.90', 'semiannual_price' => '719.40', 'annual_price' => '1318.80', 'trial_days' => 14, 'included_users' => 3, 'extra_user_price' => '19.90', 'promotional_discount_percent' => 0];

    $this->actingAs($root)->patch('/rootadmin/financeiro/plano', [...$payload, 'prices_approved' => true])->assertRedirect();
    expect(Plan::main()->pricesApproved())->toBeTrue();

    $this->actingAs($root)->patch('/rootadmin/financeiro/plano', [...$payload, 'prices_approved' => false])->assertRedirect();
    expect(Plan::main()->pricesApproved())->toBeFalse();

    $approvals = AuditLog::query()->where('action', 'billing.plan_updated')->orderBy('id')->get()
        ->map(fn (AuditLog $log): bool => $log->metadata['changes']['prices_approved_at']['para'] !== null)->all();

    expect($approvals)->toBe([true, false]);
});
