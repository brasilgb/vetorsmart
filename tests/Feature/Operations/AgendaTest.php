<?php

use App\Enums\AppointmentStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\ProfessionalBlock;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestEvent;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 60]);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->professional->services()->attach($this->service->id, ['tenant_id' => $this->tenant->id]);

    // Monday 08:00–12:00 and 13:00–18:00, in the tenant's timezone (America/Sao_Paulo).
    $this->monday = CarbonImmutable::now($this->tenant->timezone)->next(CarbonImmutable::MONDAY)->setTime(8, 0);

    foreach ([['08:00:00', '12:00:00'], ['13:00:00', '18:00:00']] as [$start, $end]) {
        ProfessionalAvailability::create([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'weekday' => 1,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    $this->request = ServiceRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'status' => RequestStatus::Triaged,
    ]);
});

function book(Tenant $tenant, ServiceRequest $request, Professional $professional, CarbonImmutable $at, User $actor)
{
    return test()->actingAs($actor)->post(route('tenant.requests.schedule', $request), [
        'professional_id' => $professional->id,
        'starts_at' => $at->format('Y-m-d H:i'),
    ]);
}

test('a time inside a working window is booked', function () {
    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner)->assertRedirect();

    expect(Appointment::query()->where('professional_id', $this->professional->id)->count())->toBe(1)
        ->and($this->request->fresh()->status)->toBe(RequestStatus::Scheduled);
});

test('a time outside every working window is refused', function () {
    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(12, 30), $this->owner)
        ->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(0);
});

test('an appointment that would overlap another one is refused', function () {
    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner)->assertRedirect();

    $second = ServiceRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'status' => RequestStatus::Triaged,
    ]);

    book($this->tenant, $second, $this->professional, $this->monday->setTime(9, 30), $this->owner)
        ->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(1);
});

test('a block on the agenda prevents booking inside it', function () {
    ProfessionalBlock::create([
        'tenant_id' => $this->tenant->id,
        'professional_id' => $this->professional->id,
        'starts_at' => $this->monday->setTime(9, 0)->utc(),
        'ends_at' => $this->monday->setTime(10, 0)->utc(),
        'reason' => 'Treinamento',
    ]);

    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner)
        ->assertSessionHasErrors('starts_at');
});

test('a professional who does not perform the service cannot be booked for it', function () {
    $other = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    ProfessionalAvailability::create([
        'tenant_id' => $this->tenant->id,
        'professional_id' => $other->id,
        'weekday' => 1,
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
    ]);

    book($this->tenant, $this->request, $other, $this->monday->setTime(9, 0), $this->owner)
        ->assertSessionHasErrors('professional_id');
});

test('an inactive professional cannot receive appointments', function () {
    $this->professional->update(['active' => false]);

    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner)
        ->assertSessionHasErrors('professional_id');
});

test('a request must be triaged before it can be booked', function () {
    $untriaged = ServiceRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status' => RequestStatus::Requested,
    ]);

    book($this->tenant, $untriaged, $this->professional, $this->monday->setTime(9, 0), $this->owner)
        ->assertSessionHasErrors('status');
});

test('rescheduling ignores the appointment itself but still respects other bookings', function () {
    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner);
    $appointment = Appointment::query()->firstOrFail();

    $this->actingAs($this->owner)
        ->patch(route('tenant.agenda.reschedule', $appointment), ['starts_at' => $this->monday->setTime(9, 30)->format('Y-m-d H:i')])
        ->assertRedirect();

    expect($this->tenant->toLocal($appointment->fresh()->starts_at)->format('H:i'))->toBe('09:30');

    $second = ServiceRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'status' => RequestStatus::Triaged,
    ]);
    book($this->tenant, $second, $this->professional, $this->monday->setTime(11, 0), $this->owner);

    $this->actingAs($this->owner)
        ->patch(route('tenant.agenda.reschedule', $appointment), ['starts_at' => $this->monday->setTime(10, 30)->format('Y-m-d H:i')])
        ->assertSessionHasErrors('starts_at');
});

test('cancelling an appointment returns the request to triage', function () {
    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner);
    $appointment = Appointment::query()->firstOrFail();

    $this->actingAs($this->owner)
        ->post(route('tenant.agenda.cancel', $appointment), ['reason' => 'Cliente pediu outro dia'])
        ->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($this->request->fresh()->status)->toBe(RequestStatus::Triaged);
});

test('the availability endpoint lists only free times, computed on the server', function () {
    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner);

    $response = $this->actingAs($this->owner)
        ->getJson(route('tenant.agenda.slots', [
            'request_id' => $this->request->id,
            'professional_id' => $this->professional->id,
            'date' => $this->monday->format('Y-m-d'),
        ]))
        ->assertOk();

    $slots = $response->json('slots');

    expect($slots)->not->toContain('09:00')
        ->and($slots)->toContain('08:00', '10:00', '13:00')
        ->and($slots)->not->toContain('12:30');
});

test('the agenda opens without parameters from the menu, and with a malformed date', function () {
    $this->actingAs($this->owner)
        ->get(route('tenant.agenda.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tenant/agenda/index')->where('date', now($this->tenant->timezone)->format('Y-m-d')));

    $this->actingAs($this->owner)
        ->get(route('tenant.agenda.index', ['date' => 'nao-e-data', 'view' => 'day']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('date', today()->format('Y-m-d')));
});

test('the agenda shows only appointments of the active company', function () {
    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner);

    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $otherTenant->id, 'user_id' => $otherUser->id]);

    $this->actingAs($otherUser)
        ->get(route('tenant.agenda.index', ['view' => 'week', 'date' => $this->monday->format('Y-m-d')]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tenant/agenda/index')->has('appointments', 0));
});

test('cancelling from the agenda records the reason, the author, the time and a history entry', function () {
    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner);
    $appointment = Appointment::query()->firstOrFail();

    $this->actingAs($this->owner)
        ->post(route('tenant.agenda.cancel', $appointment), ['reason' => 'Cliente viajou'])
        ->assertRedirect();

    $appointment->refresh();

    expect($appointment->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->cancelling_reason)->toBe('Cliente viajou')
        ->and($appointment->cancelled_by_user_id)->toBe($this->owner->id)
        ->and($appointment->cancelled_at)->not->toBeNull();

    $entry = ServiceRequestEvent::query()->where('service_request_id', $this->request->id)->latest('id')->first();

    expect($entry->note)->toContain('Cliente viajou')
        ->and($entry->user_id)->toBe($this->owner->id);
});

test('a cancellation without a reason is refused and nothing changes', function () {
    book($this->tenant, $this->request, $this->professional, $this->monday->setTime(9, 0), $this->owner);
    $appointment = Appointment::query()->firstOrFail();

    $this->actingAs($this->owner)
        ->post(route('tenant.agenda.cancel', $appointment), ['reason' => '   '])
        ->assertSessionHasErrors('reason');

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->fresh()->cancelled_at)->toBeNull();
});
