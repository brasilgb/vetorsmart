<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\BillingSource;
use App\Enums\ChargeStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\BillingCharge;
use App\Models\BillingWebhookEvent;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Services\Billing\ChargeService;
use App\Services\Billing\Efi\EfiException;
use App\Services\Billing\SubscriptionService;
use App\Support\PlatformTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subscriptions of all companies: financial situation, trials, charges, gateway failures and the actions of the
 * platform (start or extend a trial, contract terms, cancel, manual settlement, retry with Efí). Every action is
 * recorded in the subscription history and in the audit log.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->value();
        $term = $request->string('q')->trim()->value();

        $subscriptions = Subscription::query()
            ->with(['tenant:id,name,is_homolog,status', 'plan:id,name'])
            ->when(SubscriptionStatus::tryFrom($status) !== null, fn ($query) => $query->where('status', $status))
            ->when($term !== '', fn ($query) => $query->whereHas('tenant', fn ($inner) => $inner->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = Subscription::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('rootadmin/subscriptions/index', [
            'filters' => ['status' => $status, 'q' => $term],
            'statuses' => array_map(fn (SubscriptionStatus $item): array => ['value' => $item->value, 'label' => $item->label(), 'total' => (int) ($counts[$item->value] ?? 0)], SubscriptionStatus::cases()),
            'subscriptions' => $subscriptions->through(fn (Subscription $subscription): array => [
                'id' => $subscription->id,
                'tenant' => $subscription->tenant->name,
                'tenant_id' => $subscription->tenant_id,
                'is_homolog' => $subscription->tenant->isHomolog(),
                'administratively_suspended' => $subscription->tenant->status->value !== 'active',
                'plan' => $subscription->plan->name,
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'until' => PlatformTime::date(match ($subscription->status) {
                    SubscriptionStatus::Trialing => $subscription->trial_ends_at,
                    SubscriptionStatus::PastDue => $subscription->grace_ends_at,
                    default => $subscription->current_period_end,
                }),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ]),
            'failures' => [
                'charges' => BillingCharge::query()->with('tenant:id,name')->where('status', ChargeStatus::Pending->value)->where('issue_attempts', '>', 0)->latest('id')->limit(10)->get()
                    ->map(fn (BillingCharge $charge): array => ['id' => $charge->id, 'subscription_id' => $charge->subscription_id, 'tenant' => $charge->tenant->name, 'attempts' => $charge->issue_attempts, 'error' => $charge->last_error])->all(),
                'webhooks' => BillingWebhookEvent::query()->where('status', 'failed')->latest('id')->limit(10)->get()
                    ->map(fn (BillingWebhookEvent $event): array => ['id' => $event->id, 'provider' => $event->provider, 'reference' => $event->reference, 'error' => $event->error, 'at' => PlatformTime::dateTime($event->created_at)])->all(),
            ],
        ]);
    }

    public function show(Subscription $subscription): Response
    {
        $subscription->load(['tenant', 'plan']);
        $amount = $subscription->periodAmountCents();

        return Inertia::render('rootadmin/subscriptions/show', [
            'subscription' => [
                'id' => $subscription->id,
                'tenant' => $subscription->tenant->name,
                'tenant_id' => $subscription->tenant_id,
                'tenant_status' => $subscription->tenant->status->value,
                'plan' => $subscription->plan->name,
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'payment_method' => $subscription->payment_method?->label(),
                'amount' => $amount === null ? null : number_format($amount / 100, 2, ',', '.'),
                'price' => $subscription->price_cents === null ? '' : number_format($subscription->price_cents / 100, 2, '.', ''),
                'discount_percent' => $subscription->discount_percent,
                'discount_until' => $subscription->discount_until?->format('Y-m-d') ?? '',
                'trial_ends_at' => PlatformTime::dateTime($subscription->trial_ends_at),
                'current_period' => $subscription->current_period_start === null ? null : PlatformTime::date($subscription->current_period_start).' a '.PlatformTime::date($subscription->current_period_end),
                'grace_ends_at' => PlatformTime::dateTime($subscription->grace_ends_at),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ],
            'charges' => BillingCharge::query()->where('subscription_id', $subscription->id)->latest('id')->limit(50)->get()->map(fn (BillingCharge $charge): array => [
                'id' => $charge->id,
                'period' => PlatformTime::date($charge->period_start).' a '.PlatformTime::date($charge->period_end),
                'amount' => number_format($charge->amount_cents / 100, 2, ',', '.'),
                'due_date' => $charge->due_date->format('d/m/Y'),
                'method' => $charge->method->label(),
                'status' => $charge->status->value,
                'status_label' => $charge->status->label(),
                'gateway_id' => $charge->gateway_id,
                'paid_at' => PlatformTime::dateTime($charge->paid_at),
                'paid_via' => $charge->paid_via,
                'attempts' => $charge->issue_attempts,
                'error' => $charge->last_error,
            ])->all(),
            'events' => SubscriptionEvent::query()->where('subscription_id', $subscription->id)->with('user:id,name')->latest('id')->limit(100)->get()->map(fn (SubscriptionEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'from' => $event->from_status === null ? null : SubscriptionStatus::from($event->from_status)->label(),
                'to' => $event->to_status === null ? null : SubscriptionStatus::from($event->to_status)->label(),
                'source' => $event->source->label(),
                'note' => $event->note,
                'user' => $event->user?->name,
                'at' => PlatformTime::dateTime($event->created_at),
            ])->all(),
        ]);
    }

    /**
     * Start the trial of a company created before billing existed.
     */
    public function start(Request $request, Tenant $tenant, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = $subscriptions->startTrial($tenant, BillingSource::RootAdmin, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Período de teste iniciado.']);

        return to_route('rootadmin.subscriptions.show', $subscription);
    }

    public function extendTrial(Request $request, Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:90']], ['days.*' => 'Informe de 1 a 90 dias.']);

        $subscriptions->extendTrial($subscription, (int) $data['days'], $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Teste prorrogado.']);

        return back();
    }

    public function terms(Request $request, Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['nullable', 'numeric', 'min:1', 'max:99999'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'discount_until' => ['nullable', 'date'],
        ]);

        $subscriptions->setTerms(
            $subscription,
            $data['price'] === null ? null : (int) round(((float) $data['price']) * 100),
            (int) $data['discount_percent'],
            empty($data['discount_until']) ? null : Carbon::parse($data['discount_until']),
            $this->actor($request),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Condições atualizadas. Valem para as próximas cobranças.']);

        return back();
    }

    public function cancel(Request $request, Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']], ['reason.*' => 'Informe o motivo (5 a 300 caracteres).']);

        $subscriptions->cancelNow($subscription, $this->actor($request), $data['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Assinatura cancelada.']);

        return back();
    }

    public function settle(Request $request, BillingCharge $charge, ChargeService $charges): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']], ['reason.*' => 'Informe o motivo da baixa manual (5 a 300 caracteres).']);

        abort_unless($charge->status->isOpen(), 422, 'Esta cobrança não está em aberto.');

        $charges->settleManually($charge, $this->actor($request), $data['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pagamento registrado manualmente.']);

        return back();
    }

    /**
     * Retry with Efí: issue a pending charge, or read the state of a waiting one.
     */
    public function sync(BillingCharge $charge, ChargeService $charges): RedirectResponse
    {
        try {
            $charge->status === ChargeStatus::Pending ? $charges->issue($charge) : $charges->reconcile($charge);
            $charge->refresh();
            Inertia::flash('toast', $charge->last_error === null
                ? ['type' => 'success', 'message' => 'Cobrança sincronizada com a Efí.']
                : ['type' => 'error', 'message' => $charge->last_error]);
        } catch (EfiException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }
}
