<?php

/*
 * One concurrent booking attempt, run as its own PHP process (its own MySQL connection).
 * Started by parallel_booking.php, which passes a JSON payload as the only argument.
 *
 * Output (stdout, one JSON line): {"outcome": "booked"|"conflict"|"error", "appointment_id": int|null, "error": string|null}
 */

use App\Models\Professional;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\AppointmentScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payload = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

// Wait for the common start instant so the attempts overlap as much as possible.
$wait = ((int) $payload['start_at_us']) - (int) (microtime(true) * 1_000_000);
if ($wait > 0) {
    usleep($wait);
}

$result = ['outcome' => 'error', 'appointment_id' => null, 'error' => null];

try {
    $request = ServiceRequest::query()->whereKey($payload['request_id'])->firstOrFail();
    $professional = Professional::query()->whereKey($payload['professional_id'])->firstOrFail();
    $actor = $payload['actor_id'] !== null ? User::query()->whereKey($payload['actor_id'])->first() : null;

    $appointment = app(AppointmentScheduler::class)->schedule(
        $request,
        $professional,
        CarbonImmutable::parse($payload['starts_at'])->utc(),
        $actor,
    );

    $result = ['outcome' => 'booked', 'appointment_id' => $appointment->getKey(), 'error' => null];
} catch (ValidationException $exception) {
    // Expected for the losers: the slot was taken or the request was already consumed. No detail is printed.
    $result = ['outcome' => 'conflict', 'appointment_id' => null, 'error' => $exception::class];
} catch (Throwable $exception) {
    // Lock wait timeout, deadlock or other database error. Only the class name is reported.
    $result = ['outcome' => 'error', 'appointment_id' => null, 'error' => $exception::class];
}

echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
