<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ContractSubscriptionRequest;
use App\Models\BillingCharge;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\ChargeService;
use App\Services\Billing\SubscriptionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Minha Assinatura: plan, amount, due date, trial, financial situation, charges and their documents. Owners and
 * admins see it; only owners act. Reachable even while the subscription blocks the rest of the panel.
 */
class SubscriptionController extends Controller
{
    public function show(Request $request, TenantContext $context): Response
    {
        $tenant = $context->requireTenant();
        Gate::authorize('viewBilling', $tenant);

        $subscription = Subscription::query()->forTenant($tenant)->with('plan')->first();
        $charges = $subscription === null ? null : BillingCharge::query()->forTenant($tenant)->latest('id')->paginate(12);

        return Inertia::render('tenant/subscription/index', [
            'subscription' => $subscription === null ? null : $this->summary($tenant, $subscription),
            'charges' => $charges?->through(fn (BillingCharge $charge): array => [
                'id' => $charge->id,
                'period' => $charge->period_start->format('d/m/Y').' a '.$charge->period_end->format('d/m/Y'),
                'amount' => $this->money($charge->amount_cents),
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
            'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
            'hasDocument' => in_array(strlen((string) preg_replace('/\D+/', '', (string) $tenant->document)), [11, 14], true),
            'canManage' => Gate::allows('manageBilling', $tenant),
        ]);
    }

    /**
     * Choose how to pay and get the charge of the period due (created and issued at Efí when there is none open).
     */
    public function contract(ContractSubscriptionRequest $request, TenantContext $context, SubscriptionService $subscriptions, ChargeService $charges): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('manageBilling', $tenant);

        $subscription = Subscription::query()->forTenant($tenant)->firstOrFail();
        $method = PaymentMethod::from($request->string('method')->value());

        if ($method === PaymentMethod::Billet) {
            $this->ensureDocument($tenant, (string) $request->input('document', ''));
        }

        $subscriptions->choosePaymentMethod($subscription, $method, $this->actor($request));
        $charge = $charges->openFor($subscription->fresh() ?? $subscription, $method);

        Inertia::flash('toast', $charge->status === ChargeStatus::Waiting
            ? ['type' => 'success', 'message' => 'Cobrança gerada. Use o Pix ou o boleto abaixo para pagar.']
            : ['type' => 'error', 'message' => 'Não foi possível emitir a cobrança agora. Tentaremos de novo automaticamente.']);

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
        $amount = $subscription->periodAmountCents();
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
            'amount' => $amount === null ? null : $this->money($amount),
            'interval_months' => $subscription->plan->interval_months,
            'discount_percent' => $subscription->discount_percent,
            'next_due' => $nextDue === null ? null : $tenant->toLocal($nextDue)->format('d/m/Y'),
            'trial_days_left' => $subscription->trialDaysLeft(),
            'payment_method' => $subscription->payment_method?->value,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'included_users' => $subscription->plan->included_users,
            'can_contract' => $amount !== null,
            'can_cancel' => in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true) && ! $subscription->cancel_at_period_end,
        ];
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
