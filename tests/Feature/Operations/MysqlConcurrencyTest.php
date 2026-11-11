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
use App\Models\User;
use App\Services\AppointmentScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Concurrency checks against a real MySQL server. They run only when VETORSMART_CONCURRENCY=1 and
 * DB_CONNECTION=mysql, and are skipped otherwise.
 *
 * Two independent connections: "mysql" (session A) holds locks inside a transaction, and
 * "mysql_second" (session B) runs the real AppointmentScheduler. Fixtures are committed through
 * session B, because RefreshDatabase keeps session A inside an uncommitted transaction. Session B
 * waits at most one second for a lock, so a blocked attempt fails with "Lock wait timeout".
 */

/**
 * Run $callback as session A (its own transaction, committed on return, rolled back on exception).
 */
function asSessionA(callable $callback): mixed
{
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql_first');

    try {
        return DB::transaction(fn () => $callback());
    } finally {
        DB::setDefaultConnection($previous);
    }
}

/**
 * Run $callback as session B (autocommit, outside any transaction of session A).
 */
function asSessionB(callable $callback): mixed
{
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql_second');

    try {
        return $callback();
    } finally {
        DB::setDefaultConnection($previous);
    }
}

beforeEach(function () {
    if (config('database.default') !== 'mysql' || env('VETORSMART_CONCURRENCY') !== '1') {
        $this->markTestSkipped('Concorrência: defina VETORSMART_CONCURRENCY=1 com DB_CONNECTION=mysql.');
    }

    // Separate connections: RefreshDatabase holds an outer transaction on "mysql", which would turn
    // session A's transactions into savepoints that never release their locks.
    config([
        'database.connections.mysql_first' => config('database.connections.mysql'),
        'database.connections.mysql_second' => config('database.connections.mysql'),
    ]);
    DB::purge('mysql_first');
    DB::purge('mysql_second');
    DB::connection('mysql_second')->statement('SET SESSION innodb_lock_wait_timeout = 1');
    DB::setDefaultConnection('mysql_second');

    $this->tenant = Tenant::factory()->create(['name' => '[CONCORRENCIA] Teste']);
    $this->actor = User::factory()->create();
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 60]);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->professional->services()->attach($this->service->id, ['tenant_id' => $this->tenant->id]);

    ProfessionalAvailability::create([
        'tenant_id' => $this->tenant->id,
        'professional_id' => $this->professional->id,
        'weekday' => 1,
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
    ]);

    // Next Monday, 09:00 in São Paulo time, as a UTC instant.
    $this->startsAt = CarbonImmutable::now('America/Sao_Paulo')->next(CarbonImmutable::MONDAY)->setTime(9, 0)->utc();
    $this->scheduler = app(AppointmentScheduler::class);
});

afterEach(function () {
    DB::setDefaultConnection('mysql_second');

    if (isset($this->tenant)) {
        foreach (['appointments', 'service_request_events', 'service_requests', 'professional_blocks', 'professional_services', 'professional_availabilities', 'professionals', 'services', 'customers', 'audit_logs', 'tenant_users'] as $table) {
            DB::table($table)->where('tenant_id', $this->tenant->id)->delete();
        }

        DB::table('tenants')->where('id', $this->tenant->id)->delete();
    }

    if (isset($this->actor)) {
        DB::table('users')->where('id', $this->actor->id)->delete();
    }

    DB::setDefaultConnection('mysql');
    DB::purge('mysql_second');
});

/**
 * A triaged request for the fixture customer and service, committed through session B.
 */
function triagedRequest(): ServiceRequest
{
    return ServiceRequest::factory()->create([
        'tenant_id' => test()->tenant->id,
        'customer_id' => test()->customer->id,
        'service_id' => test()->service->id,
        'status' => RequestStatus::Triaged,
    ]);
}

test('a booking waits for the professional lock held by another session, then sees the committed booking', function () {
    $first = triagedRequest();
    $second = triagedRequest();

    asSessionA(function () use ($first) {
        Professional::query()->whereKey($this->professional->id)->lockForUpdate()->firstOrFail();

        // Session B cannot take the professional lock while A holds it.
        expect(fn () => asSessionB(fn () => $this->scheduler->schedule($first, $this->professional, $this->startsAt, $this->actor)))
            ->toThrow(QueryException::class, 'Lock wait timeout');
    });

    // A has committed and released its lock: the first booking is now stored.
    asSessionB(fn () => $this->scheduler->schedule($first, $this->professional, $this->startsAt, $this->actor));

    // A duplicate attempt for the same slot is refused and nothing else is written.
    expect(fn () => asSessionB(fn () => $this->scheduler->schedule($second, $this->professional, $this->startsAt, $this->actor)))
        ->toThrow(ValidationException::class);

    expect(Appointment::query()->where('professional_id', $this->professional->id)->count())->toBe(1)
        ->and($second->fresh()->status)->toBe(RequestStatus::Triaged);
});

test('a rolled back booking leaves no trace and the slot stays free', function () {
    $request = triagedRequest();
    $other = triagedRequest();

    try {
        asSessionA(function () use ($request) {
            Appointment::create([
                'tenant_id' => $this->tenant->id,
                'service_request_id' => $request->id,
                'professional_id' => $this->professional->id,
                'starts_at' => $this->startsAt,
                'ends_at' => $this->startsAt->addHour(),
                'status' => AppointmentStatus::Scheduled,
                'created_by_user_id' => $this->actor->id,
            ]);

            throw new RuntimeException('rollback intencional');
        });
    } catch (RuntimeException) {
        // Expected: session A's transaction is rolled back.
    }

    expect(Appointment::query()->where('professional_id', $this->professional->id)->count())->toBe(0);

    asSessionB(fn () => $this->scheduler->schedule($request, $this->professional, $this->startsAt, $this->actor));

    expect(fn () => asSessionB(fn () => $this->scheduler->schedule($other, $this->professional, $this->startsAt, $this->actor)))
        ->toThrow(ValidationException::class)
        ->and(Appointment::query()->where('professional_id', $this->professional->id)->count())->toBe(1);
});

test('a block committed by another session prevents a concurrent booking of the same time', function () {
    $request = triagedRequest();

    asSessionA(function () {
        // The block takes the professional lock; session B waits for it while A is still open.
        $this->scheduler->block($this->tenant, $this->professional, $this->startsAt, $this->startsAt->addHour(), 'Treinamento', $this->actor);

        expect(fn () => asSessionB(fn () => $this->scheduler->schedule(triagedRequest(), $this->professional, $this->startsAt, $this->actor)))
            ->toThrow(QueryException::class, 'Lock wait timeout');
    });

    expect(fn () => asSessionB(fn () => $this->scheduler->schedule($request, $this->professional, $this->startsAt, $this->actor)))
        ->toThrow(ValidationException::class);

    expect(Appointment::query()->where('professional_id', $this->professional->id)->count())->toBe(0);
});

test('a service deactivated by another session cannot be booked', function () {
    $request = triagedRequest();

    asSessionB(fn () => Service::query()->whereKey($this->service->id)->update(['active' => false]));

    expect(fn () => asSessionB(fn () => $this->scheduler->schedule($request, $this->professional, $this->startsAt, $this->actor)))
        ->toThrow(ValidationException::class);

    expect(Appointment::query()->where('professional_id', $this->professional->id)->count())->toBe(0);
});

test('two sessions cannot reschedule the same appointment at the same time', function () {
    $request = triagedRequest();
    $appointment = asSessionB(fn () => $this->scheduler->schedule($request, $this->professional, $this->startsAt, $this->actor));

    asSessionA(function () use ($appointment) {
        Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

        expect(fn () => asSessionB(fn () => $this->scheduler->reschedule($appointment, $this->startsAt->addHour(), $this->actor)))
            ->toThrow(QueryException::class, 'Lock wait timeout');
    });

    expect($appointment->fresh()->starts_at->format('Y-m-d H:i'))->toBe($this->startsAt->format('Y-m-d H:i'));
});
