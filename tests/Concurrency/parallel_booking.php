<?php

/*
 * Real concurrency check for the booking path, with separate PHP processes and separate MySQL connections.
 *
 *   php tests/Concurrency/parallel_booking.php
 *
 * Refuses to run unless the configured database name ends with "_test", and writes only test rows tagged
 * "[CONCORRENCIA]". Scenarios:
 *   A. N triaged requests disputing the same professional and the same instant: exactly one appointment.
 *   B. The same triaged request sent N times (duplicate submission): exactly one appointment, one request consumed.
 *
 * Exit code 0 when every expectation holds, 1 otherwise.
 */

use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.mysql.database');
if (config('database.default') !== 'mysql' || ! str_ends_with($database, '_test')) {
    fwrite(STDERR, "Recusado: o banco '{$database}' não termina em _test. Use somente o banco de testes.\n");
    exit(2);
}

const PARALLEL_ATTEMPTS = 6;

$workerScript = __DIR__.'/worker.php';
$failures = [];

/**
 * Create the fixtures with autocommit, so that every worker process sees them.
 *
 * @return array{tenant: Tenant, customer: Customer, service: Service, professional: Professional, startsAt: CarbonImmutable}
 */
function seedFixtures(): array
{
    $tenant = Tenant::factory()->create(['name' => '[CONCORRENCIA] Paralelo '.bin2hex(random_bytes(3))]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'duration_minutes' => 60]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $professional->services()->attach($service->id, ['tenant_id' => $tenant->id]);

    ProfessionalAvailability::create([
        'tenant_id' => $tenant->id,
        'professional_id' => $professional->id,
        'weekday' => 1,
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
    ]);

    // Next Monday, 09:00 in São Paulo time, as a UTC instant.
    $startsAt = CarbonImmutable::now('America/Sao_Paulo')->next(CarbonImmutable::MONDAY)->setTime(9, 0)->utc();

    return compact('tenant', 'customer', 'service', 'professional', 'startsAt');
}

/**
 * @param  array{tenant: Tenant, customer: Customer, service: Service, professional: Professional, startsAt: CarbonImmutable}  $fixtures
 */
function triaged(array $fixtures): ServiceRequest
{
    return ServiceRequest::factory()->create([
        'tenant_id' => $fixtures['tenant']->id,
        'customer_id' => $fixtures['customer']->id,
        'service_id' => $fixtures['service']->id,
        'status' => RequestStatus::Triaged,
    ]);
}

/**
 * Start one worker process per payload at the same instant and collect each JSON result.
 *
 * @param  list<array<string, mixed>>  $payloads
 * @return list<array<string, mixed>>
 */
function runParallel(string $workerScript, array $payloads): array
{
    $startAt = (int) ((microtime(true) + 3.0) * 1_000_000);
    $processes = [];

    foreach ($payloads as $payload) {
        $payload['start_at_us'] = $startAt;
        $command = [PHP_BINARY, $workerScript, json_encode($payload, JSON_THROW_ON_ERROR)];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, dirname($workerScript, 2));

        if (! is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar o processo de teste.');
        }

        $processes[] = [$process, $pipes[1]];
    }

    $results = [];

    foreach ($processes as [$process, $stdout]) {
        $line = trim((string) stream_get_contents($stdout));
        fclose($stdout);
        proc_close($process);

        $decoded = json_decode($line, true);
        $results[] = is_array($decoded) ? $decoded : ['outcome' => 'error', 'appointment_id' => null, 'error' => 'saída inválida'];
    }

    return $results;
}

function countAppointments(Tenant $tenant, Professional $professional, CarbonImmutable $startsAt): int
{
    return Appointment::query()
        ->where('tenant_id', $tenant->id)
        ->where('professional_id', $professional->id)
        ->where('starts_at', $startsAt->utc()->toDateTimeString())
        ->count();
}

function cleanup(Tenant $tenant): void
{
    foreach (['appointments', 'service_request_events', 'service_requests', 'professional_blocks', 'professional_services', 'professional_availabilities', 'professionals', 'services', 'customers', 'audit_logs', 'tenant_users'] as $table) {
        DB::table($table)->where('tenant_id', $tenant->id)->delete();
    }

    DB::table('tenants')->where('id', $tenant->id)->delete();
}

// --- Scenario A: distinct requests, same professional and instant -------------------------------------------------
$fixtures = seedFixtures();
try {
    $requests = [];
    for ($i = 0; $i < PARALLEL_ATTEMPTS; $i++) {
        $requests[] = triaged($fixtures);
    }

    $payloads = array_map(fn (ServiceRequest $request) => [
        'request_id' => $request->getKey(),
        'professional_id' => $fixtures['professional']->getKey(),
        'starts_at' => $fixtures['startsAt']->toIso8601String(),
        'actor_id' => null,
    ], $requests);

    $results = runParallel($workerScript, $payloads);
    $booked = count(array_filter($results, fn ($r) => $r['outcome'] === 'booked'));
    $errors = array_values(array_filter($results, fn ($r) => $r['outcome'] === 'error'));
    $stored = countAppointments($fixtures['tenant'], $fixtures['professional'], $fixtures['startsAt']);

    echo "[A] {$booked} agendado(s), ".(PARALLEL_ATTEMPTS - $booked).' recusado(s), '.count($errors)." erro(s) técnico(s); agendamentos gravados: {$stored}\n";

    if ($booked !== 1 || $stored !== 1) {
        $failures[] = "[A] esperado exatamente 1 agendamento; obtido booked={$booked}, gravados={$stored}";
    }
    foreach ($errors as $error) {
        // Lock wait timeouts and deadlocks are acceptable only as clean refusals; they must not leave partial rows.
        echo "    erro técnico: {$error['error']}\n";
    }
} finally {
    cleanup($fixtures['tenant']);
}

// --- Scenario B: the same request sent several times (duplicate submission) ----------------------------------------
$fixtures = seedFixtures();
try {
    $request = triaged($fixtures);
    $payload = [
        'request_id' => $request->getKey(),
        'professional_id' => $fixtures['professional']->getKey(),
        'starts_at' => $fixtures['startsAt']->toIso8601String(),
        'actor_id' => null,
    ];

    $results = runParallel($workerScript, array_fill(0, PARALLEL_ATTEMPTS, $payload));
    $booked = count(array_filter($results, fn ($r) => $r['outcome'] === 'booked'));
    $stored = countAppointments($fixtures['tenant'], $fixtures['professional'], $fixtures['startsAt']);
    $requestsAfter = ServiceRequest::query()->where('tenant_id', $fixtures['tenant']->id)->count();

    echo "[B] {$booked} agendado(s) para a mesma solicitação; agendamentos gravados: {$stored}; solicitações: {$requestsAfter}\n";

    if ($booked !== 1 || $stored !== 1) {
        $failures[] = "[B] esperado exatamente 1 agendamento para a mesma solicitação; obtido booked={$booked}, gravados={$stored}";
    }
} finally {
    cleanup($fixtures['tenant']);
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FALHA: {$failure}\n");
    }
    exit(1);
}

echo "Concorrência real: todas as verificações aprovadas.\n";
exit(0);
