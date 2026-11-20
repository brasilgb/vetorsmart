<?php

use App\Enums\AppointmentStatus;
use App\Enums\ConversationStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\AssistantConversation;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * OPERATIONS-01: the dashboard as a work center. Queues of what needs action, with the actions each member may take
 * through the existing routes, which keep validating the status of the request.
 */
beforeEach(function () {
    Notification::fake();

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id, 'role' => 'operator']);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maria Souza']);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação']);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Carlos']);
});

function workRequest(Tenant $tenant, Customer $customer, RequestStatus $status, array $attributes = []): ServiceRequest
{
    return ServiceRequest::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'status' => $status, ...$attributes]);
}

function workAppointment(ServiceRequest $request, Professional $professional, CarbonInterface $startsAt): Appointment
{
    return Appointment::factory()->create(['tenant_id' => $request->tenant_id, 'service_request_id' => $request->id, 'professional_id' => $professional->id, 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHour(), 'status' => AppointmentStatus::Scheduled]);
}

/**
 * The queues of the dashboard, keyed by name.
 *
 * @return array<string, array<string, mixed>>
 */
function workQueues(User $user): array
{
    $props = test()->actingAs($user)->get('/dashboard')->assertOk()->viewData('page')['props']['work'];

    return collect($props['queues'])->keyBy('key')->all() + ['today' => $props['today'], 'upcoming' => $props['upcoming']];
}

test('the work center lists what needs action, most urgent first, with the right action for each status', function () {
    workRequest($this->tenant, $this->customer, RequestStatus::Requested);
    $triaged = workRequest($this->tenant, $this->customer, RequestStatus::Triaged, ['service_id' => $this->service->id]);
    $inProgress = workRequest($this->tenant, $this->customer, RequestStatus::InProgress, ['service_id' => $this->service->id]);
    $late = workRequest($this->tenant, $this->customer, RequestStatus::Scheduled, ['service_id' => $this->service->id]);
    workAppointment($late, $this->professional, now()->subHours(3));
    workRequest($this->tenant, $this->customer, RequestStatus::Completed, ['closed_at' => now()->subDay()]);
    AssistantConversation::query()->create(['tenant_id' => $this->tenant->id, 'channel' => 'site', 'session_hash' => str_repeat('a', 64), 'state' => 'human', 'status' => ConversationStatus::AwaitingHuman, 'collected' => [], 'handoff_reason' => 'Quer falar com uma pessoa.', 'last_activity_at' => now(), 'retention_until' => now()->addDays(30)]);

    $props = $this->actingAs($this->owner)->get('/dashboard')->viewData('page')['props']['work'];
    $queues = collect($props['queues'])->keyBy('key');

    expect(collect($props['queues'])->pluck('key')->all())->toBe(['triage', 'human', 'late', 'schedule', 'in_progress', 'amount'])
        ->and($queues['triage']['items'][0]['actions'])->toHaveKeys(['triage', 'cancel'])
        ->and($queues['schedule']['items'][0]['actions']['schedule'])->toBe("/solicitacoes/{$triaged->id}#agendamento")
        ->and($queues['in_progress']['items'][0]['actions'])->toBe(['complete' => "/solicitacoes/{$inProgress->id}/concluir"])
        ->and($queues['late']['items'][0]['actions'])->toHaveKeys(['start', 'cancel'])
        ->and($queues['late']['items'][0]['professional'])->toBe('Carlos')
        ->and($queues['amount']['items'][0]['actions'])->toHaveKey('amount')
        ->and($queues['human']['items'][0]['assume'])->not->toBeNull();
});

test('today and the next days show the visits in the company calendar', function () {
    $this->travelTo(now()->setTimezone($this->tenant->timezone)->setTime(8, 0)->utc());
    $today = workRequest($this->tenant, $this->customer, RequestStatus::Scheduled, ['service_id' => $this->service->id]);
    workAppointment($today, $this->professional, now()->addHours(2));
    $later = workRequest($this->tenant, $this->customer, RequestStatus::Scheduled, ['service_id' => $this->service->id]);
    workAppointment($later, $this->professional, now()->addDays(3));

    $queues = workQueues($this->owner);

    expect($queues['today']['count'])->toBe(1)
        ->and($queues['today']['items'][0]['when'])->toStartWith('Hoje, ')
        ->and($queues['today']['items'][0]['actions'])->toHaveKey('start')
        ->and($queues['upcoming']['count'])->toBe(1)
        // A visit of another day is not started from the dashboard.
        ->and($queues['upcoming']['items'][0]['actions'])->not->toHaveKey('start');
});

test('nothing of another company appears, and its records cannot be moved from here', function () {
    $other = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->create(['tenant_id' => $other->id]);
    $foreign = workRequest($other, $otherCustomer, RequestStatus::Requested);
    AssistantConversation::query()->create(['tenant_id' => $other->id, 'channel' => 'site', 'session_hash' => str_repeat('b', 64), 'state' => 'human', 'status' => ConversationStatus::AwaitingHuman, 'collected' => [], 'last_activity_at' => now(), 'retention_until' => now()->addDays(30)]);

    $this->actingAs($this->owner)->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->where('work.queues', [])->where('work.summary.open', 0));
    $this->actingAs($this->owner)->post("/solicitacoes/{$foreign->id}/cancelar", ['reason' => 'Tentativa indevida.'])->assertNotFound();

    expect($foreign->refresh()->status)->toBe(RequestStatus::Requested);
});

test('operators get the operational actions but cannot correct an amount already recorded', function () {
    workRequest($this->tenant, $this->customer, RequestStatus::Requested);
    $recorded = workRequest($this->tenant, $this->customer, RequestStatus::Completed, ['closed_at' => now(), 'realized_amount_cents' => 1000]);

    $queues = workQueues($this->operator);

    expect($queues['triage']['items'][0]['actions'])->toHaveKeys(['triage', 'cancel'])
        ->and(array_key_exists('amount', $queues))->toBeFalse();

    $this->actingAs($this->operator)->patch("/solicitacoes/{$recorded->id}/valor", ['realized_amount' => '1,00'])->assertForbidden();
});

test('quick actions go through the existing routes: start, complete with amount, and cancel only with a reason', function () {
    $scheduled = workRequest($this->tenant, $this->customer, RequestStatus::Scheduled, ['service_id' => $this->service->id]);
    workAppointment($scheduled, $this->professional, now()->subMinutes(30));
    $open = workRequest($this->tenant, $this->customer, RequestStatus::Requested);

    $this->actingAs($this->operator)->from('/dashboard')->post("/solicitacoes/{$scheduled->id}/iniciar")->assertRedirect('/dashboard');
    // A second tap on a stale screen never repeats the transition.
    $this->actingAs($this->operator)->from('/dashboard')->post("/solicitacoes/{$scheduled->id}/iniciar")->assertSessionHasErrors();
    $this->actingAs($this->operator)->from('/dashboard')->post("/solicitacoes/{$scheduled->id}/concluir", ['realized_amount' => '250,00'])->assertRedirect('/dashboard');

    $this->actingAs($this->operator)->from('/dashboard')->post("/solicitacoes/{$open->id}/cancelar", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->operator)->from('/dashboard')->post("/solicitacoes/{$open->id}/cancelar", ['reason' => 'Cliente desistiu.'])->assertRedirect('/dashboard');

    expect($scheduled->refresh()->only(['status', 'realized_amount_cents']))->toBe(['status' => RequestStatus::Completed, 'realized_amount_cents' => 25000])
        ->and($scheduled->events()->pluck('to_status')->filter()->values()->all())->toContain('in_progress', 'completed')
        ->and($open->refresh()->status)->toBe(RequestStatus::Cancelled);
});

test('an empty company sees no pending work and an empty agenda', function () {
    $this->actingAs($this->owner)->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('work.queues', [])
        ->where('work.today.count', 0)
        ->where('work.upcoming.count', 0));
});
