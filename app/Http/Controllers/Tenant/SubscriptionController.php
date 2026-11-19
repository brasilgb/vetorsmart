<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AiCreditPurchaseStatus;
use App\Enums\BillingInterval;
use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ContractSubscriptionRequest;
use App\Models\AiCreditEntry;
use App\Models\AiCreditPackage;
use App\Models\AiCreditPurchase;
use App\Models\BillingCharge;
use App\Models\BillingSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\AiCreditPurchaseService;
use App\Services\Billing\AiCreditService;
use App\Services\Billing\ChargeService;
use App\Services\Billing\PlanCatalog;
use App\Services\Billing\SeatService;
use App\Services\Billing\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Minha Assinatura: plan and cycle, next charge with its breakdown, users, AI credits and their consumption, charges
 * and payment documents. Owners and admins see it; only owners act. Reachable even while the subscription blocks
 * the rest of the panel.
 */
class SubscriptionController extends Controller
{
    public function show(Request $request, TenantContext $context, PlanCatalog $catalog, AiCreditService $credits): Response
    {
        $tenant = $context->requireTenant();
        Gate::authorize('viewBilling', $tenant);

        $subscription = Subscription::query()->forTenant($tenant)->with('plan')->first();
        $charges = $subscription === null ? null : BillingCharge::query()->forTenant($tenant)->latest('id')->paginate(12);

        return Inertia::render('tenant/subscription/index', [
            'subscription' => $subscription === null ? null : $this->summary($tenant, $subscription),
            'offer' => $subscription === null ? null : $this->presentOffer($catalog->offer($subscription->plan)),
            'charges' => $charges?->through(fn (BillingCharge $charge): array => [
                'id' => $charge->id,
                'period' => $charge->period_start->format('d/m/Y').' a '.$charge->period_end->format('d/m/Y'),
                'interval_label' => $charge->billing_interval->label(),
                'amount' => $this->money($charge->amount_cents),
                'breakdown' => $this->chargeBreakdown($charge),
                'due_date' => $charge->due_date->format('d/m/Y'),
                'status' => $charge->status->value,
                'status_label' => $charge->status->label(),
                'method_label' => $charge->method->label(),
                'paid_at' => $charge->paid_at === null ? null : $tenant->toLocal($charge->paid_at)->format('d/m/Y H:i'),
                'pix_copy_paste' => $charge->status === ChargeStatus::Waiting ? $charge->pix_copy_paste : null,
                'billet_url' => $charge->status === ChargeStatus::Waiting ? $this->safeUrl($charge->billet_url) : null,
                'billet_pdf' => $this->safeUrl($charge->billet_pdf),
                'issue_failed' => $charge->status === ChargeStatus::Pending && $charge->issue_attempts > 0,
            ]),
            'credits' => $subscription === null ? null : $this->creditsSummary($tenant, $credits),
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
            'hasDocument' => in_array(strlen((string) preg_replace('/\D+/', '', (string) $tenant->document)), [11, 14], true),
            'canManage' => Gate::allows('manageBilling', $tenant),
        ]);
    }

    /**
     * Choose the cycle and how to pay, and get the charge of the period due (created and issued at Efí when there is
     * none open).
     */
    public function contract(ContractSubscriptionRequest $request, TenantContext $context, SubscriptionService $subscriptions, ChargeService $charges): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageBilling', $tenant);

        $subscription = Subscription::query()->forTenant($tenant)->firstOrFail();
        $method = PaymentMethod::from($request->string('method')->value());
        $actor = $this->actor($request);

        if ($method === PaymentMethod::Billet) {
            $this->ensureDocument($tenant, (string) $request->input('document', ''));
        }

        if ($request->filled('interval')) {
            $subscription = $subscriptions->changeInterval($subscription, BillingInterval::from($request->string('interval')->value()), $actor);
        }

        $subscriptions->choosePaymentMethod($subscription, $method, $actor);
        $charge = $charges->openFor($subscription->fresh() ?? $subscription, $method);

        Inertia::flash('toast', $charge->status === ChargeStatus::Waiting
            ? ['type' => 'success', 'message' => 'Cobrança gerada. Use o Pix ou o boleto abaixo para pagar.']
            : ['type' => 'error', 'message' => 'Não foi possível emitir a cobrança agora. Tentaremos de novo automaticamente.']);

        return to_route('tenant.subscription.show');
    }

    /**
     * Change the billing cycle (see SubscriptionService::changeInterval for when it applies).
     */
    public function interval(Request $request, TenantContext $context, SubscriptionService $subscriptions): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageBilling', $tenant);

        $data = $request->validate(['interval' => ['required', Rule::enum(BillingInterval::class)]], ['interval.*' => 'Escolha um ciclo válido.']);
        $subscription = $subscriptions->changeInterval(Subscription::query()->forTenant($tenant)->firstOrFail(), BillingInterval::from($data['interval']), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => $subscription->scheduled_interval !== null
            ? 'Troca de ciclo agendada para o próximo período.'
            : 'Ciclo atualizado.']);

        return to_route('tenant.subscription.show');
    }

    /**
     * Contract more or fewer additional user seats (see SeatService for when a change applies and is charged).
     */
    public function seats(Request $request, TenantContext $context, SeatService $seats): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageBilling', $tenant);

        $data = $request->validate(['extra_seats' => ['required', 'integer', 'min:0', 'max:500']], ['extra_seats.*' => 'Informe de 0 a 500 vagas adicionais.']);
        $subscription = $seats->changeExtraSeats(Subscription::query()->forTenant($tenant)->firstOrFail(), (int) $data['extra_seats'], $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => $subscription->scheduled_extra_seats !== null
            ? 'Alteração de vagas incluída na próxima cobrança. As novas vagas valem depois do pagamento.'
            : 'Vagas atualizadas. Elas entram na primeira cobrança.']);

        return to_route('tenant.subscription.show');
    }

    public function buyCredits(Request $request, TenantContext $context, AiCreditPurchaseService $purchases): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageBilling', $tenant);

        $data = $request->validate(['package_id' => ['required', 'integer']], ['package_id.*' => 'Escolha um pacote.']);
        $package = AiCreditPackage::query()->whereKey($data['package_id'])->first();

        if ($package === null) {
            throw ValidationException::withMessages(['package' => 'Este pacote não está disponível.']);
        }

        $purchases->request($tenant, $package, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compra registrada. Os créditos entram no saldo após a confirmação do pagamento.']);

        return to_route('tenant.subscription.show');
    }

    public function cancel(Request $request, TenantContext $context, SubscriptionService $subscriptions): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageBilling', $tenant);

        $subscriptions->requestCancellation(Subscription::query()->forTenant($tenant)->firstOrFail(), $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cancelamento agendado para o fim do período.']);

        return to_route('tenant.subscription.show');
    }

    public function resume(Request $request, TenantContext $context, SubscriptionService $subscriptions): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageBilling', $tenant);

        $subscriptions->resume(Subscription::query()->forTenant($tenant)->firstOrFail(), $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cancelamento desfeito. A assinatura continua.']);

        return to_route('tenant.subscription.show');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Tenant $tenant, Subscription $subscription): array
    {
        $quote = $subscription->quote($subscription->scheduled_interval ?? $subscription->billing_interval);
        $nextDue = match ($subscription->status) {
            SubscriptionStatus::Trialing => $subscription->trial_ends_at,
            SubscriptionStatus::Active => $subscription->current_period_end,
            SubscriptionStatus::PastDue => $subscription->grace_ends_at,
            default => null,
        };

        return [
            'plan' => $subscription->plan->name,
            'plan_description' => $subscription->plan->description,
            'status' => $subscription->status->value,
            'status_label' => $subscription->status->label(),
            'interval' => $subscription->billing_interval->value,
            'interval_label' => $subscription->billing_interval->label(),
            'scheduled_interval' => $subscription->scheduled_interval?->value,
            'scheduled_interval_label' => $subscription->scheduled_interval?->label(),
            'next_charge' => ($open = $this->openCharge($subscription)) !== null ? [
                'interval_label' => $open->billing_interval->label(),
                'base' => $this->money($open->base_amount_cents ?? $open->amount_cents),
                'discount' => $open->discount_cents > 0 ? $this->money($open->discount_cents) : null,
                'extra_users' => $open->extra_users,
                'extra_user_price' => $open->extra_users > 0 ? $this->money(intdiv($open->extra_users_amount_cents, max(1, $open->extra_users * $open->billing_interval->months()))) : $this->money(0),
                'extra_users_amount' => $open->extra_users_amount_cents > 0 ? $this->money($open->extra_users_amount_cents) : null,
                'total' => $this->money($open->amount_cents),
                'months' => $open->billing_interval->months(),
                'open' => true,
            ] : ($quote === null ? null : [
                'interval_label' => $quote['interval']->label(),
                'base' => $this->money($quote['base_cents']),
                'discount' => $quote['discount_cents'] > 0 ? $this->money($quote['discount_cents']) : null,
                'extra_users' => $quote['extra_users'],
                'extra_user_price' => $this->money($quote['extra_user_price_cents']),
                'extra_users_amount' => $quote['extra_users_cents'] > 0 ? $this->money($quote['extra_users_cents']) : null,
                'total' => $this->money($quote['total_cents']),
                'months' => $quote['months'],
                'open' => false,
            ]),
            'price_frozen' => $subscription->price_cents !== null,
            'discount_percent' => $subscription->discount_percent,
            'discount_until' => $subscription->discount_until?->format('d/m/Y'),
            'next_due' => $nextDue === null ? null : $tenant->toLocal($nextDue)->format('d/m/Y'),
            'trial_days_left' => $subscription->trialDaysLeft(),
            'payment_method' => $subscription->payment_method?->value,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'included_users' => $subscription->plan->included_users,
            'active_users' => $quote['active_users'] ?? null,
            'extra_seats' => $subscription->extra_seats,
            'scheduled_extra_seats' => $subscription->scheduled_extra_seats,
            'seats_change_mode' => match (true) {
                ! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true) => 'blocked',
                $subscription->status === SubscriptionStatus::Trialing && $this->openCharge($subscription) === null => 'now',
                default => 'next_charge',
            },
            'seat_limit' => $subscription->plan->included_users === null ? null : $subscription->plan->included_users + $subscription->extra_seats,
            'extra_user_price' => ($price = $subscription->extra_user_price_cents ?? $subscription->plan->extra_user_price_cents) === null ? null : $this->money($price),
            'can_contract' => $quote !== null,
            'can_cancel' => in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true) && ! $subscription->cancel_at_period_end,
        ];
    }

    /**
     * The open charge is the next one the company pays, with the amount fixed when it was created.
     */
    private function openCharge(Subscription $subscription): ?BillingCharge
    {
        return BillingCharge::query()->where('subscription_id', $subscription->getKey())
            ->whereIn('status', [ChargeStatus::Pending->value, ChargeStatus::Waiting->value])
            ->orderBy('period_start')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    private function presentOffer(array $offer): array
    {
        return [
            ...$offer,
            'extra_user_price' => $offer['extra_user_price_cents'] === null ? null : $this->money($offer['extra_user_price_cents']),
            'cycles' => array_map(fn (array $cycle): array => [
                ...$cycle,
                'total' => $this->money($cycle['total_cents']),
                'monthly_equivalent' => $this->money($cycle['monthly_equivalent_cents']),
            ], $offer['cycles']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function creditsSummary(Tenant $tenant, AiCreditService $credits): array
    {
        $account = $credits->account($tenant);
        $settings = BillingSetting::current();

        return [
            'enforced' => $credits->enforced($tenant),
            'franchise' => $credits->franchise($tenant),
            'included_balance' => max(0, $account->included_balance),
            'purchased_balance' => max(0, $account->purchased_balance),
            'total_balance' => $account->totalBalance(),
            'period' => $credits->currentPeriod($tenant),
            'used_this_month' => (int) AiCreditEntry::query()->forTenant($tenant)->where('kind', 'consumption')->where('created_at', '>=', now($tenant->timezone)->startOfMonth()->utc())->sum('credits'),
            'entries' => AiCreditEntry::query()->forTenant($tenant)->latest('id')->limit(20)->get()->map(fn (AiCreditEntry $entry): array => [
                'id' => $entry->id,
                'kind' => $entry->kind,
                'kind_label' => $entry->kindLabel(),
                'credits' => $entry->included_delta + $entry->purchased_delta,
                'consumed' => $entry->kind === 'consumption' ? $entry->credits : null,
                'operation' => $entry->operation,
                'at' => $entry->created_at === null ? null : $tenant->toLocal($entry->created_at)->format('d/m/Y H:i'),
            ])->all(),
            'purchases_enabled' => $settings->ai_credit_purchases_enabled,
            'packages' => AiCreditPackage::query()->where('active', true)->orderBy('credits')->get()->map(fn (AiCreditPackage $package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'credits' => $package->credits,
                'price' => $this->money($package->price_cents),
            ])->all(),
            'purchases' => AiCreditPurchase::query()->forTenant($tenant)->latest('id')->limit(10)->get()->map(fn (AiCreditPurchase $purchase): array => [
                'id' => $purchase->id,
                'package' => $purchase->package_name,
                'credits' => $purchase->credits,
                'price' => $this->money($purchase->price_cents),
                'status' => $purchase->status->value,
                'status_label' => $purchase->status->label(),
                'at' => $purchase->created_at === null ? null : $tenant->toLocal($purchase->created_at)->format('d/m/Y'),
            ])->all(),
            'has_open_purchase' => AiCreditPurchase::query()->forTenant($tenant)->where('status', AiCreditPurchaseStatus::AwaitingPayment->value)->exists(),
        ];
    }

    /**
     * @return list<string>
     */
    private function chargeBreakdown(BillingCharge $charge): array
    {
        if ($charge->base_amount_cents === null) {
            return [];
        }

        $lines = ['Plano '.$charge->billing_interval->label().': '.$this->money($charge->base_amount_cents)];

        if ($charge->discount_cents > 0) {
            $lines[] = 'Desconto: −'.$this->money($charge->discount_cents);
        }

        if ($charge->extra_users > 0) {
            $lines[] = "{$charge->extra_users} vaga(s) adicional(is): ".$this->money($charge->extra_users_amount_cents);
        }

        return $lines;
    }

    private function ensureDocument(Tenant $tenant, string $typed): void
    {
        $digits = (string) preg_replace('/\D+/', '', $typed !== '' ? $typed : (string) $tenant->document);

        if (! in_array(strlen($digits), [11, 14], true)) {
            throw ValidationException::withMessages(['document' => 'Para o boleto, informe o CPF (11 dígitos) ou o CNPJ (14 dígitos) da empresa.']);
        }

        if ($typed !== '' && $digits !== preg_replace('/\D+/', '', (string) $tenant->document)) {
            $tenant->forceFill(['document' => $digits])->save();
        }
    }

    private function money(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Only https links from the gateway are shown.
     */
    private function safeUrl(?string $url): ?string
    {
        return $url !== null && str_starts_with($url, 'https://') ? $url : null;
    }
}
