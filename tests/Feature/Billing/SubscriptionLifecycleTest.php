<?php

use App\Enums\BillingSource;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Segment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\BillingCycle;
use App\Services\Billing\SubscriptionService;
use App\Services\TenantStatusService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    enableBillingEnforcement();
    Notification::fake();

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    $this->operator = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id]);

    $this->service = app(SubscriptionService::class);
});

function lifecycleSubscription(Tenant $tenant): Subscription
{
    return app(SubscriptionService::class)->startTrial($tenant, BillingSource::System);
}

test('a public registration starts the 14-day trial of the main plan', function () {
    $segment = Segment::factory()->create();

    $this->post(route('register.store'), [
        'company_name' => 'Empresa Nova', 'segment_id' => $segment->id, 'name' => 'Dona', 'email' => 'dona@example.com',
        'password' => 'Senha-Forte-2026', 'password_confirmation' => 'Senha-Forte-2026',
    ])->assertRedirect();

    $subscription = Subscription::query()->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->plan->code)->toBe(Plan::MAIN)
        ->and((int) round(now()->diffInDays($subscription->trial_ends_at)))->toBe(14)
        ->and($subscription->events()->value('type'))->toBe('trial_started');
});

test('starting the trial twice keeps a single subscription', function () {
    $first = lifecycleSubscription($this->tenant);
    $second = lifecycleSubscription($this->tenant);

    expect($second->id)->toBe($first->id)
        ->and(Subscription::query()->count())->toBe(1);
});

test('a transition outside the allowed ones is refused and changes nothing', function () {
    $subscription = lifecycleSubscription($this->tenant);

    expect(fn () => $this->service->transition($subscription, SubscriptionStatus::Suspended, BillingSource::System))
        ->toThrow(ValidationException::class);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->events()->count())->toBe(1);
});

test('the end of the trial without payment opens the grace period, and the end of the grace suspends', function () {
    $subscription = lifecycleSubscription($this->tenant);

    $this->travel(15)->days();
    app(BillingCycle::class)->run();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->grace_ends_at->isFuture())->toBeTrue();

    $this->travel(6)->days();
    app(BillingCycle::class)->run();
    app(BillingCycle::class)->run();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Suspended)
        ->and($this->tenant->fresh()->status)->toBe(TenantStatus::Active)
        ->and($subscription->events()->where('to_status', 'suspended')->count())->toBe(1);
});

test('a cancellation requested during the trial closes the subscription when the trial ends', function () {
    $subscription = lifecycleSubscription($this->tenant);
    $this->actingAs($this->owner)->post('/assinatura/cancelar')->assertRedirect('/assinatura');

    $this->travel(15)->days();
    app(BillingCycle::class)->run();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled);
});

test('the owners are warned once that the trial is about to end', function () {
    lifecycleSubscription($this->tenant);

    $this->travel(12)->days();
    app(BillingCycle::class)->run();
    app(BillingCycle::class)->run();

    expect(TenantNotification::query()->where('user_id', $this->owner->id)->where('title', 'Seu período de teste termina em breve')->count())->toBe(1)
        ->and(TenantNotification::query()->where('user_id', $this->operator->id)->count())->toBe(0);
});

test('a financially suspended company keeps only Minha Assinatura open', function () {
    $subscription = lifecycleSubscription($this->tenant);
    $subscription->forceFill(['status' => SubscriptionStatus::Suspended])->save();

    $this->actingAs($this->owner)->get('/clientes')->assertRedirect(route('tenant.subscription.show'));
    $this->actingAs($this->owner)->get('/assinatura')->assertOk();
    $this->actingAs($this->operator)->get('/clientes')->assertStatus(402)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/subscription/blocked'));
    $this->actingAs($this->owner)->getJson('/api/v1/tenant/customers')->assertStatus(402);
});

test('a company created before billing, without a subscription, keeps its access', function () {
    $this->actingAs($this->owner)->get('/clientes')->assertOk();
    $this->actingAs($this->owner)->get('/assinatura')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('subscription', null));
});

test('the administrative suspension and the financial one never overwrite each other', function () {
    $subscription = lifecycleSubscription($this->tenant);
    $root = User::factory()->rootAdmin()->create();

    app(TenantStatusService::class)->change($this->tenant, TenantStatus::Suspended, $root);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Trialing);

    app(TenantStatusService::class)->change($this->tenant->fresh(), TenantStatus::Active, $root);
    $this->service->transition($subscription, SubscriptionStatus::PastDue, BillingSource::System);
    $this->service->transition($subscription->fresh(), SubscriptionStatus::Suspended, BillingSource::System);

    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Active);
});

test('operators cannot see the subscription and admins cannot act on it', function () {
    lifecycleSubscription($this->tenant);
    $admin = User::factory()->create();
    TenantUser::factory()->admin()->create(['tenant_id' => $this->tenant->id, 'user_id' => $admin->id]);

    $this->actingAs($this->operator)->get('/assinatura')->assertForbidden();
    $this->actingAs($admin)->get('/assinatura')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('canManage', false));
    $this->actingAs($admin)->post('/assinatura/cancelar')->assertForbidden();
    $this->actingAs($admin)->post('/assinatura/contratar', ['method' => 'pix'])->assertForbidden();
});
