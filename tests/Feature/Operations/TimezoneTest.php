<?php

use App\Enums\RequestStatus;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AppointmentScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
 * Timezone rules: instants are stored in UTC, displayed in the tenant's timezone, and the history shows the local time.
 * Old history entries are not rewritten by these rules.
 */

beforeEach(function () {
    $this->actor = User::factory()->create();
    $this->scheduler = app(AppointmentScheduler::class);
});

/**
 * Tenant, service and professional in the given timezone, available every day from 08:00 to 18:00 local time.
 *
 * @return array{tenant: Tenant, service: Service, professional: Professional}
 */
function timezoneFixture(string $timezone): array
{
    $tenant = Tenant::factory()->create(['timezone' => $timezone]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id, 'active' => true]);
    $professional->services()->attach($service->id, ['tenant_id' => $tenant->id]);

    foreach (range(0, 6) as $weekday) {
        ProfessionalAvailability::create([
            'tenant_id' => $tenant->id,
            'professional_id' => $professional->id,
            'weekday' => $weekday,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
        ]);
    }

    return ['tenant' => $tenant, 'service' => $service, 'professional' => $professional];
}

/**
 * A triaged request for the fixture tenant.
 */
function triagedFor(Tenant $tenant, Service $service): ServiceRequest
{
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    return ServiceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status' => RequestStatus::Triaged,
    ]);
}

dataset('local_bookings', [
    'Manaus (UTC-4, sem horário de verão)' => ['America/Manaus', '2027-07-12 09:00', '2027-07-12 13:00:00'],
    'São Paulo (UTC-3)' => ['America/Sao_Paulo', '2027-07-12 09:00', '2027-07-12 12:00:00'],
    'Nova York em julho (UTC-4, verão)' => ['America/New_York', '2027-07-12 09:00', '2027-07-12 13:00:00'],
    'Nova York em janeiro (UTC-5, inverno)' => ['America/New_York', '2027-01-12 09:00', '2027-01-12 14:00:00'],
    'Lisboa em julho (UTC+1, verão)' => ['Europe/Lisbon', '2027-07-12 09:00', '2027-07-12 08:00:00'],
]);

test('a booking is stored in UTC and shown back in the tenant timezone', function (string $timezone, string $local, string $utc) {
    ['tenant' => $tenant, 'service' => $service, 'professional' => $professional] = timezoneFixture($timezone);
    $localStart = CarbonImmutable::parse($local, $timezone);

    $appointment = $this->scheduler->schedule(triagedFor($tenant, $service), $professional, $localStart->utc(), $this->actor);

    // Storage: the raw column holds UTC.
    expect(DB::table('appointments')->where('id', $appointment->getKey())->value('starts_at'))->toBe($utc)
        // Display: the tenant timezone gives back the local time that was chosen.
        ->and($tenant->toLocal($appointment->fresh()->starts_at)->format('Y-m-d H:i'))->toBe($localStart->format('Y-m-d H:i'));
})->with('local_bookings');

test('the request history shows the local time of the booking, not UTC', function (string $timezone, string $local, string $utc) {
    ['tenant' => $tenant, 'service' => $service, 'professional' => $professional] = timezoneFixture($timezone);
    $localStart = CarbonImmutable::parse($local, $timezone);
    $request = triagedFor($tenant, $service);

    $this->scheduler->schedule($request, $professional, $localStart->utc(), $this->actor);

    $note = ServiceRequestEvent::query()
        ->where('service_request_id', $request->getKey())
        ->where('to_status', RequestStatus::Scheduled->value)
        ->value('note');

    expect($note)->toBe('Agendado para '.$localStart->format('d/m/Y H:i').'.');
})->with('local_bookings');

test('a reschedule keeps UTC storage and the local time in the tenant timezone', function () {
    ['tenant' => $tenant, 'service' => $service, 'professional' => $professional] = timezoneFixture('America/Manaus');
    $appointment = $this->scheduler->schedule(triagedFor($tenant, $service), $professional, CarbonImmutable::parse('2027-07-12 09:00', 'America/Manaus')->utc(), $this->actor);

    $newLocal = CarbonImmutable::parse('2027-07-12 15:30', 'America/Manaus');
    $this->scheduler->reschedule($appointment, $newLocal->utc(), $this->actor);

    expect(DB::table('appointments')->where('id', $appointment->getKey())->value('starts_at'))->toBe('2027-07-12 19:30:00')
        ->and($tenant->toLocal($appointment->fresh()->starts_at)->format('d/m/Y H:i'))->toBe('12/07/2027 15:30');
});

test('two tenants in different timezones do not interfere with each other’s local times', function () {
    $manaus = timezoneFixture('America/Manaus');
    $lisbon = timezoneFixture('Europe/Lisbon');

    $a = $this->scheduler->schedule(triagedFor($manaus['tenant'], $manaus['service']), $manaus['professional'], CarbonImmutable::parse('2027-07-12 09:00', 'America/Manaus')->utc(), $this->actor);
    $b = $this->scheduler->schedule(triagedFor($lisbon['tenant'], $lisbon['service']), $lisbon['professional'], CarbonImmutable::parse('2027-07-12 09:00', 'Europe/Lisbon')->utc(), $this->actor);

    expect($manaus['tenant']->toLocal($a->fresh()->starts_at)->format('H:i'))->toBe('09:00')
        ->and($lisbon['tenant']->toLocal($b->fresh()->starts_at)->format('H:i'))->toBe('09:00')
        ->and($a->fresh()->starts_at->format('H:i'))->toBe('13:00')
        ->and($b->fresh()->starts_at->format('H:i'))->toBe('08:00');
});

test('a booking at a local hour that is on the next UTC day keeps the correct local date in history', function () {
    // 22:00 in Manaus is 02:00 UTC of the next day: the UTC date differs from the local date.
    ['tenant' => $tenant, 'service' => $service, 'professional' => $professional] = timezoneFixture('America/Manaus');
    ProfessionalAvailability::query()->where('professional_id', $professional->id)->update(['end_time' => '23:00:00']);

    $request = triagedFor($tenant, $service);
    $local = CarbonImmutable::parse('2027-07-12 22:00', 'America/Manaus');
    $this->scheduler->schedule($request, $professional, $local->utc(), $this->actor);

    $note = ServiceRequestEvent::query()->where('service_request_id', $request->getKey())->where('to_status', RequestStatus::Scheduled->value)->value('note');

    expect($note)->toBe('Agendado para 12/07/2027 22:00.')
        ->and(DB::table('appointments')->where('service_request_id', $request->getKey())->value('starts_at'))->toBe('2027-07-13 02:00:00');
});
