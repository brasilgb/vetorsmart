<?php

use App\Enums\AppointmentStatus;
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
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Sao_Paulo']);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 60]);
    $this->day = CarbonImmutable::now($this->tenant->timezone)->next(CarbonImmutable::MONDAY)->setTime(10, 0);
});

function requestFor(): int
{
    return ServiceRequest::factory()->create([
        'tenant_id' => test()->tenant->id,
        'customer_id' => test()->customer->id,
        'service_id' => test()->service->id,
        'status' => RequestStatus::Scheduled,
    ])->id;
}

test('a completed visit stays on the agenda with its status and no reschedule or cancel action', function () {
    $completed = Appointment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'service_request_id' => requestFor(),
        'professional_id' => $this->professional->id,
        'starts_at' => $this->day->utc(),
        'ends_at' => $this->day->addHour()->utc(),
        'status' => AppointmentStatus::Completed,
    ]);

    $this->actingAs($this->owner)
        ->get(route('tenant.agenda.index', ['view' => 'week', 'date' => $this->day->format('Y-m-d')]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/agenda/index')
            ->where('appointments', fn ($appointments) => collect($appointments)->contains(fn (array $item): bool => $item['id'] === $completed->id
                && $item['status'] === 'completed'
                && $item['status_label'] === 'Concluído'
                && $item['can_change'] === false)));
});

test('a scheduled visit can be rescheduled or cancelled, and cancelled visits keep their history', function () {
    $scheduled = Appointment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'service_request_id' => requestFor(),
        'professional_id' => $this->professional->id,
        'starts_at' => $this->day->utc(),
        'ends_at' => $this->day->addHour()->utc(),
        'status' => AppointmentStatus::Scheduled,
    ]);

    $cancelled = Appointment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'service_request_id' => requestFor(),
        'professional_id' => $this->professional->id,
        'starts_at' => $this->day->addHours(2)->utc(),
        'ends_at' => $this->day->addHours(3)->utc(),
        'status' => AppointmentStatus::Cancelled,
    ]);

    $this->actingAs($this->owner)
        ->get(route('tenant.agenda.index', ['view' => 'week', 'date' => $this->day->format('Y-m-d')]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('appointments', fn ($appointments) => collect($appointments)->contains(fn (array $item): bool => $item['id'] === $scheduled->id && $item['can_change'] === true)
                && collect($appointments)->contains(fn (array $item): bool => $item['id'] === $cancelled->id && $item['status_label'] === 'Cancelado' && $item['can_change'] === false)));
});

test('a JSON reschedule returns 200 when it succeeds and a field error when the slot is occupied', function () {
    $this->professional->services()->attach($this->service->id, ['tenant_id' => $this->tenant->id]);
    ProfessionalAvailability::create([
        'tenant_id' => $this->tenant->id,
        'professional_id' => $this->professional->id,
        'weekday' => 1,
        'start_time' => '08:00:00',
        'end_time' => '18:00:00',
    ]);

    $moving = Appointment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'service_request_id' => requestFor(),
        'professional_id' => $this->professional->id,
        'starts_at' => $this->day->utc(),
        'ends_at' => $this->day->addHour()->utc(),
        'status' => AppointmentStatus::Scheduled,
    ]);

    Appointment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'service_request_id' => requestFor(),
        'professional_id' => $this->professional->id,
        'starts_at' => $this->day->addHours(3)->utc(),
        'ends_at' => $this->day->addHours(4)->utc(),
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($this->owner)
        ->patchJson(route('tenant.agenda.reschedule', $moving), ['starts_at' => $this->day->addHours(6)->format('Y-m-d H:i')])
        ->assertOk()
        ->assertJsonPath('starts_at', $this->day->addHours(6)->format('Y-m-d H:i'));

    $this->actingAs($this->owner)
        ->patchJson(route('tenant.agenda.reschedule', $moving), ['starts_at' => $this->day->addHours(3)->format('Y-m-d H:i')])
        ->assertStatus(422)
        ->assertJsonValidationErrors('starts_at');

    expect($moving->fresh()->starts_at->eq($this->day->addHours(6)->utc()))->toBeTrue();
});
