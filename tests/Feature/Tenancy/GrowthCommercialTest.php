<?php

use App\Enums\ConversationStatus;
use App\Enums\DiscoverySource;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\AssistantConversation;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Assistant\AssistantOperations;
use App\Services\Growth\GrowthMetrics;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * GROWTH-01: Meu Crescimento as a commercial tool. Conversion by cohort, average ticket without unknown amounts,
 * new and recurring customers, closing reasons, deterministic recommendations and links to the records.
 */
beforeEach(function () {
    Notification::fake();

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->other = Tenant::factory()->create();
    $this->otherCustomer = Customer::factory()->create(['tenant_id' => $this->other->id]);
});

function growthRequest(Tenant $tenant, Customer $customer, RequestStatus $status, array $attributes = []): ServiceRequest
{
    $request = ServiceRequest::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'status' => $status, ...$attributes]);

    if (array_key_exists('created_at', $attributes)) {
        $request->forceFill(['created_at' => $attributes['created_at']])->save();
    }

    return $request;
}

test('conversion follows the requests received in the period, and the ticket ignores services without an amount', function () {
    $now = now()->subMinutes(5);
    growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['created_at' => $now, 'closed_at' => $now, 'realized_amount_cents' => 10000]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['created_at' => $now, 'closed_at' => $now, 'realized_amount_cents' => 10001]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['created_at' => $now, 'closed_at' => $now]);
    foreach (range(1, 3) as $index) {
        growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => $now]);
    }
    // Completed now but received two months ago: a completion of the period, not a conversion of its requests.
    growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['created_at' => now()->subMonths(2), 'closed_at' => $now, 'realized_amount_cents' => 99900]);
    growthRequest($this->other, $this->otherCustomer, RequestStatus::Completed, ['created_at' => $now, 'closed_at' => $now, 'realized_amount_cents' => 500000]);

    $this->actingAs($this->owner)->get('/meu-crescimento')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('current.requests', 6)
            ->where('current.converted', 3)
            ->where('current.conversion', 50)
            ->where('current.completed', 4)
            ->where('current.revenue', 'R$ 1.199,01')
            ->where('current.revenue_unknown', 1)
            // (10000 + 10001 + 99900) / 3 = 39967 cents: services without an amount are left out of the average.
            ->where('current.ticket', 'R$ 399,67'));
});

test('the average ticket rounds half up with integers and is absent without amounts', function () {
    $metrics = app(GrowthMetrics::class);

    expect($metrics->averageTicket(20001, 2))->toBe(10001)
        ->and($metrics->averageTicket(20000, 3))->toBe(6667)
        ->and($metrics->averageTicket(0, 0))->toBeNull();
});

test('relationship comes from completed services (GROWTH-02): returning, recurring, without recent service and frequency', function () {
    // Came back to hire in the period: a service completed 40 days ago and another one now.
    $returning = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $returning->forceFill(['created_at' => now()->subDays(200)])->save();
    growthRequest($this->tenant, $returning, RequestStatus::Completed, ['closed_at' => now()->subDays(40)]);
    growthRequest($this->tenant, $returning, RequestStatus::Completed, ['closed_at' => now()->subDay()]);
    // Requests only, never a completed service: not recurring, whatever the number of requests.
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested);
    growthRequest($this->tenant, $this->customer, RequestStatus::Cancelled, ['closed_at' => now()]);
    // Served long ago: without recent service in the last 30 days.
    $quiet = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quiet->forceFill(['created_at' => now()->subDays(200)])->save();
    growthRequest($this->tenant, $quiet, RequestStatus::Completed, ['closed_at' => now()->subDays(50)]);
    // Another company never counts.
    growthRequest($this->other, $this->otherCustomer, RequestStatus::Completed, ['closed_at' => now()]);

    $this->actingAs($this->owner)->get('/meu-crescimento?periodo=30-dias')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('relationship.new', 1)
            ->where('relationship.served', 1)
            ->where('relationship.returning', 1)
            ->where('relationship.recurring', 1)
            ->where('relationship.without_recent', 1)
            ->where('relationship.frequency_days', 39)
            ->where('relationship.days', 30));
});

test('cancellations are listed with their reasons, most frequent first', function () {
    foreach (['Cliente desistiu', 'Cliente desistiu', 'Sem peça em estoque'] as $reason) {
        growthRequest($this->tenant, $this->customer, RequestStatus::Cancelled, ['closed_at' => now()->subMinutes(5), 'closing_reason' => $reason]);
    }
    growthRequest($this->other, $this->otherCustomer, RequestStatus::Cancelled, ['closed_at' => now()->subMinutes(5), 'closing_reason' => 'Outra empresa']);

    $this->actingAs($this->owner)->get('/meu-crescimento')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('closingReasons.0', ['reason' => 'Cliente desistiu', 'status' => RequestStatus::Cancelled->label(), 'total' => 2])
            ->has('closingReasons', 2));
});

test('recommendations point to what is pending in the company, with links to the records', function () {
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested);
    growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['closed_at' => now()->subDay()]);
    $stalled = growthRequest($this->tenant, $this->customer, RequestStatus::Triaged, ['created_at' => now()->subDays(20)]);
    $stalled->events()->delete();
    $scheduled = growthRequest($this->tenant, $this->customer, RequestStatus::Scheduled);
    Appointment::factory()->create(['tenant_id' => $this->tenant->id, 'service_request_id' => $scheduled->id, 'professional_id' => Professional::factory()->create(['tenant_id' => $this->tenant->id])->id, 'starts_at' => now()->addHours(20), 'ends_at' => now()->addHours(21)]);
    AssistantConversation::query()->create(['tenant_id' => $this->tenant->id, 'channel' => 'site', 'session_hash' => str_repeat('a', 64), 'state' => 'human', 'status' => ConversationStatus::AwaitingHuman, 'collected' => [], 'last_activity_at' => now(), 'retention_until' => now()->addDays(30)]);
    // Another company's pending work never shows here.
    growthRequest($this->other, $this->otherCustomer, RequestStatus::Requested);

    $this->actingAs($this->owner)->get('/meu-crescimento')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('recommendations', fn ($items) => collect($items)->pluck('count', 'key')->all() === ['triage' => 1, 'human' => 1, 'stalled' => 1, 'upcoming' => 1, 'amount' => 1]
            && collect($items)->firstWhere('key', 'amount')['href'] === '/solicitacoes?status=completed&sem_valor=1'));
});

test('a fall in conversion is recommended only when both periods have enough requests', function () {
    $previous = now()->startOfMonth()->subMonthNoOverflow()->addDays(2);
    foreach (range(1, 5) as $index) {
        growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['created_at' => $previous, 'closed_at' => $previous]);
        growthRequest($this->tenant, $this->customer, RequestStatus::Cancelled, ['created_at' => now()->subMinutes(5), 'closed_at' => now()->subMinutes(5)]);
    }

    $this->actingAs($this->owner)->get('/meu-crescimento')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('recommendations', fn ($items) => collect($items)->firstWhere('key', 'conversion')['title'] === 'Conversão caiu 100 pontos percentuais'));

    ServiceRequest::query()->where('created_at', '>=', now()->startOfMonth())->limit(2)->delete();

    $this->actingAs($this->owner)->get('/meu-crescimento')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('recommendations', fn ($items) => collect($items)->firstWhere('key', 'conversion') === null));
});

test('the links of Meu Crescimento open the matching records in the request list', function () {
    $closed = now()->subMinutes(5);
    growthRequest($this->tenant, $this->customer, RequestStatus::Cancelled, ['closed_at' => $closed]);
    growthRequest($this->tenant, $this->customer, RequestStatus::NotPerformed, ['closed_at' => $closed]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['closed_at' => $closed]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['closed_at' => $closed, 'realized_amount_cents' => 100]);
    $stalled = growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => now()->subDays(20)]);
    $stalled->events()->delete();
    growthRequest($this->other, $this->otherCustomer, RequestStatus::Cancelled, ['closed_at' => $closed]);

    $links = $this->actingAs($this->owner)->get('/meu-crescimento')->viewData('page')['props']['links'];

    $count = fn (string $url): int => $this->actingAs($this->owner)->get($url)->viewData('page')['props']['requests']['total'];

    expect($count($links['cancelled']))->toBe(2)
        ->and($count($links['completed']))->toBe(2)
        ->and($count($links['without_amount']))->toBe(1)
        ->and($count('/solicitacoes?parada=1'))->toBe(1);
});

test('a conversation handed from the assistant to the team keeps the AI origin on its request', function () {
    $conversation = AssistantConversation::query()->create(['tenant_id' => $this->tenant->id, 'channel' => 'site', 'session_hash' => str_repeat('b', 64), 'state' => 'human', 'status' => ConversationStatus::HumanAssumed, 'collected' => [], 'last_activity_at' => now(), 'retention_until' => now()->addDays(30)]);

    $request = app(AssistantOperations::class)->identifyConversation($this->tenant, $conversation, 'Maria Souza', '11988887777', null, $this->owner);

    expect($request->origin)->toBe(RequestOrigin::Ai);
});

/*
| GROWTH-02: funnel, stock, commercial origin and rolling periods.
*/

test('the funnel follows one cohort without double counting, and a later stage always implies the earlier ones', function () {
    $now = now()->subMinutes(5);
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => $now]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Triaged, ['created_at' => $now]);
    $scheduled = growthRequest($this->tenant, $this->customer, RequestStatus::Scheduled, ['created_at' => $now]);
    Appointment::factory()->create(['tenant_id' => $this->tenant->id, 'service_request_id' => $scheduled->id, 'professional_id' => $professional->id]);
    // Completed without any history entry (old record): still counted in every earlier stage.
    $completed = growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['created_at' => $now, 'closed_at' => $now]);
    $completed->events()->delete();
    // Cancelled before triage: received only, and lost.
    growthRequest($this->tenant, $this->customer, RequestStatus::Cancelled, ['created_at' => $now, 'closed_at' => $now]);
    // Received before the period: never part of this funnel, but it is in the stock of today.
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => now()->subDays(100)]);

    $this->actingAs($this->owner)->get('/meu-crescimento?periodo=30-dias')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('funnel', ['received' => 5, 'triaged' => 3, 'scheduled' => 2, 'completed' => 1, 'lost' => 1])
            ->where('stock', ['requested' => 2, 'triaged' => 1, 'scheduled' => 1, 'in_progress' => 0]));
});

test('the commercial origin is kept apart from the channel, and unidentified requests are shown apart', function () {
    $now = now()->subMinutes(5);
    // Portal form: channel and discovery portal. Assistant after a portal arrival: channel AI, discovery portal.
    growthRequest($this->tenant, $this->customer, RequestStatus::Completed, ['created_at' => $now, 'closed_at' => $now, 'origin' => RequestOrigin::Portal, 'discovery' => DiscoverySource::Portal, 'realized_amount_cents' => 20000]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => $now, 'origin' => RequestOrigin::Ai, 'discovery' => DiscoverySource::Portal]);
    // Assistant of a direct visit: AI channel, but never attributed to the portal (or to anything) by passing there.
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => $now, 'origin' => RequestOrigin::Ai]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => $now, 'origin' => RequestOrigin::Panel]);

    $this->actingAs($this->owner)->get('/meu-crescimento?periodo=30-dias')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('discovery.0', ['discovery' => 'portal', 'label' => 'Portal Regional', 'received' => 2, 'completed' => 1, 'revenue_cents' => 20000, 'revenue_known' => 1, 'revenue' => 'R$ 200,00'])
            ->where('discovery.1.discovery', 'unknown')
            ->where('discovery.1.received', 2)
            ->where('origins', fn ($rows) => collect($rows)->firstWhere('origin', 'ai')['received'] === 2 && collect($rows)->firstWhere('origin', 'portal')['received'] === 1));
});

test('rolling periods compare with the same number of days just before, without a percentage over a zero base', function () {
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => now()->subDays(10)]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => now()->subDays(20)]);
    growthRequest($this->tenant, $this->customer, RequestStatus::Requested, ['created_at' => now()->subDays(45)]);

    $this->actingAs($this->owner)->get('/meu-crescimento?periodo=30-dias')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('current.requests', 2)->where('previous.requests', 1)->where('comparison.requests', 100)
        ->where('current.completed', 0)->where('comparison.completed', null));

    $this->actingAs($this->owner)->get('/meu-crescimento?periodo=90-dias')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('current.requests', 3)->where('previous.requests', 0)->where('comparison.requests', null));

    // An unknown period falls back to the default.
    $this->actingAs($this->owner)->get('/meu-crescimento?periodo=7-dias')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('filters.periodo', 'mes'));
});

test('an empty company sees zeros and empty states, never invented figures', function () {
    $this->actingAs($this->owner)->get('/meu-crescimento?periodo=60-dias')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('funnel', ['received' => 0, 'triaged' => 0, 'scheduled' => 0, 'completed' => 0, 'lost' => 0])
        ->where('current.revenue', null)
        ->where('current.ticket', null)
        ->where('relationship.frequency_days', null)
        ->where('hasActivity', false));
});
