<?php

use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 60]);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->professional->services()->attach($this->service->id, ['tenant_id' => $this->tenant->id]);

    // Next Monday in São Paulo time (UTC-3, no daylight saving).
    $this->monday = CarbonImmutable::now('America/Sao_Paulo')->next(CarbonImmutable::MONDAY)->startOfDay();

    foreach ([['08:00:00', '12:00:00'], ['13:00:00', '18:00:00'], ['22:00:00', '23:59:00']] as [$start, $end]) {
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

function bookLocal(Tenant $tenant, ServiceRequest $request, Professional $professional, string $localStart, User $actor)
{
    return test()->actingAs($actor)->post(route('tenant.requests.schedule', $request), [
        'professional_id' => $professional->id,
        'starts_at' => $localStart,
    ]);
}

test('a time typed in the tenant timezone is stored as the matching UTC instant', function () {
    bookLocal($this->tenant, $this->request, $this->professional, $this->monday->format('Y-m-d').' 09:00', $this->owner)->assertRedirect();

    $appointment = Appointment::query()->firstOrFail();

    expect($appointment->starts_at->format('Y-m-d H:i'))->toBe($this->monday->format('Y-m-d').' 12:00');
});

test('working windows are checked against the tenant local time', function () {
    bookLocal($this->tenant, $this->request, $this->professional, $this->monday->format('Y-m-d').' 15:30', $this->owner)->assertRedirect();

    $second = ServiceRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'status' => RequestStatus::Triaged,
    ]);

    bookLocal($this->tenant, $second, $this->professional, $this->monday->format('Y-m-d').' 18:30', $this->owner)
        ->assertSessionHasErrors('starts_at');

    expect(Appointment::query()->count())->toBe(1);
});

test('the agenda shows appointments in the tenant local time', function () {
    bookLocal($this->tenant, $this->request, $this->professional, $this->monday->format('Y-m-d').' 09:00', $this->owner)->assertRedirect();

    $this->actingAs($this->owner)
        ->get(route('tenant.agenda.index', ['view' => 'week', 'date' => $this->monday->format('Y-m-d')]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('appointments.0.date', $this->monday->format('Y-m-d'))
            ->where('appointments.0.time', '09:00')
            ->where('appointments.0.ends', '10:00'));
});

test('an appointment late at night local time stays on its local day, not on the next UTC day', function () {
    bookLocal($this->tenant, $this->request, $this->professional, $this->monday->format('Y-m-d').' 22:30', $this->owner)->assertRedirect();

    $nextDay = $this->monday->addDay()->format('Y-m-d');

    $this->actingAs($this->owner)
        ->get(route('tenant.agenda.index', ['view' => 'day', 'date' => $this->monday->format('Y-m-d')]))
        ->assertInertia(fn ($page) => $page->has('appointments', 1)->where('appointments.0.time', '22:30'));

    $this->actingAs($this->owner)
        ->get(route('tenant.agenda.index', ['view' => 'day', 'date' => $nextDay]))
        ->assertInertia(fn ($page) => $page->has('appointments', 0));
});
