<?php

use App\Enums\AssistantState;
use App\Enums\ConversationStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestEvent;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AppointmentScheduler;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

/*
 * Screens show instants in the tenant's timezone. The tenant is in Manaus (UTC-4), and the clock is set to
 * 10:00 local time on 12/07/2027 (14:00 UTC), so UTC values differ from the local ones by four hours.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Manaus']);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $this->travelTo(CarbonImmutable::parse('2027-07-12 10:00', 'America/Manaus'));
});

/**
 * A scheduled appointment of the test tenant starting at the given local time in Manaus.
 */
function manausAppointment(Tenant $tenant, string $localStart): Appointment
{
    $startsAt = CarbonImmutable::parse($localStart, 'America/Manaus')->utc();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    return Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'service_request_id' => ServiceRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'status' => RequestStatus::Scheduled,
        ])->id,
        'professional_id' => Professional::factory()->create(['tenant_id' => $tenant->id])->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHour(),
    ]);
}

test('the dashboard counts today’s appointments by the local day and shows local start times', function () {
    // 22:00 local on the 12th is 02:00 UTC on the 13th: it belongs to the local today.
    manausAppointment($this->tenant, '2027-07-12 22:00');
    // 23:30 local on the 11th is 03:30 UTC on the 12th: same UTC date as now, but it was yesterday locally.
    manausAppointment($this->tenant, '2027-07-11 23:30');

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('operations.today_appointments', 1)
            ->where('operations.upcoming.0.starts_at', '12/07/2027 22:00'));
});

test('the request list shows the creation time in the tenant timezone', function () {
    ServiceRequest::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id]);

    $this->actingAs($this->owner)
        ->get(route('tenant.requests.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('requests.data.0.created_at', '12/07/2027 10:00'));
});

test('cancelling an appointment records the local time in the request history', function () {
    $appointment = manausAppointment($this->tenant, '2027-07-13 09:00');

    app(AppointmentScheduler::class)->cancel($appointment, 'Cliente viajou.', $this->owner);

    $note = ServiceRequestEvent::query()
        ->where('service_request_id', $appointment->service_request_id)
        ->where('to_status', RequestStatus::Triaged->value)
        ->value('note');

    expect($note)->toContain('em 12/07/2027 10:00: Cliente viajou.');
});

test('assistant messages show the local time in the panel', function () {
    $conversation = AssistantConversation::create([
        'tenant_id' => $this->tenant->id,
        'channel' => 'site',
        'session_hash' => hash('sha256', 'visitante-local'),
        'state' => AssistantState::Greeting,
        'status' => ConversationStatus::Active,
        'collected' => [],
        'last_activity_at' => now(),
        'retention_until' => now()->addDays(30),
    ]);
    AssistantMessage::create(['tenant_id' => $this->tenant->id, 'assistant_conversation_id' => $conversation->id, 'role' => 'visitor', 'body' => 'Olá', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($this->owner)
        ->get(route('tenant.assistant.index', ['conversation' => $conversation->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('selected.messages.0.at', '12/07 10:00'));
});

test('the website page shows its dates in the tenant timezone', function () {
    $this->actingAs($this->owner)
        ->get(route('tenant.website.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('website.updated_at', '12/07/2027 10:00'));
});
