<?php

use App\Enums\AssistantState;
use App\Enums\ConversationStatus;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\AssistantConversation;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestEvent;
use App\Services\AppointmentScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\Support\VisitorSite;
use Tests\TestCase;

/*
 * Product rule: when the booking fails, the triage request is preserved.
 *
 * - No appointment is confirmed to the visitor, and none is stored.
 * - The request stays in a recoverable state (triaged, with its service) for the operator.
 * - The operator can retry the booking, and a retry never creates a second appointment.
 * - The operator sees a clear reason with a reference; the log carries the same reference, and no SQL or personal data.
 * - The request history shows the real result: it never shows "scheduled".
 */

/**
 * Drives the visitor to the moment of booking: identified, on a chosen slot, waiting for "sim".
 *
 * @return array{token: string, conversation: AssistantConversation, slotAt: CarbonImmutable}
 */
function visitorReadyToBook(TestCase $test): array
{
    $company = $test->company;
    $start = VisitorSite::start($test, $company['host']);
    VisitorSite::say($test, $company['host'], $start['token'], 'limpeza');
    VisitorSite::say($test, $company['host'], $start['token'], 'Maria Souza 11 98765-4321');
    VisitorSite::say($test, $company['host'], $start['token'], 'amanhã');
    VisitorSite::say($test, $company['host'], $start['token'], '1');

    return [
        'token' => $start['token'],
        'conversation' => AssistantConversation::query()->where('tenant_id', $company['tenant']->id)->sole(),
        'slotAt' => CarbonImmutable::now('America/Sao_Paulo')->addDay()->setTime(8, 0),
    ];
}

beforeEach(function () {
    $this->company = VisitorSite::publish($this, 'Clima Total', 'clima-total');
    $this->failingScheduler = function (): void {
        $scheduler = Mockery::mock(AppointmentScheduler::class);
        $scheduler->shouldReceive('schedule')->once()->andThrow(new QueryException(
            'mysql',
            'insert into `appointments` (`tenant_id`, `professional_id`) values (?, ?)',
            [1, 2],
            new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'),
        ));
        app()->instance(AppointmentScheduler::class, $scheduler);
    };
});

test('a failed booking preserves the triage request in a recoverable state', function () {
    $visitor = visitorReadyToBook($this);
    ($this->failingScheduler)();

    VisitorSite::say($this, $this->company['host'], $visitor['token'], 'sim');

    $request = ServiceRequest::query()->where('tenant_id', $this->company['tenant']->id)->sole();
    expect($request->status)->toBe(RequestStatus::Triaged)
        ->and($request->service_id)->toBe($this->company['service']->id)
        ->and($request->origin)->toBe(RequestOrigin::Ai)
        ->and($visitor['conversation']->fresh()->service_request_id)->toBe($request->getKey());
});

test('a failed booking is never confirmed to the visitor nor stored as an appointment', function () {
    $visitor = visitorReadyToBook($this);
    ($this->failingScheduler)();

    $reply = VisitorSite::say($this, $this->company['host'], $visitor['token'], 'sim')->getContent();

    expect($reply)->not->toContain('Agendamento confirmado')
        ->and($reply)->not->toContain('SQLSTATE')
        ->and($reply)->not->toContain('appointments')
        ->and(Appointment::query()->count())->toBe(0)
        ->and($visitor['conversation']->fresh()->appointment_id)->toBeNull()
        ->and($visitor['conversation']->fresh()->state)->not->toBe(AssistantState::Done);
});

test('the operator receives the conversation handed over with a reference, and the log carries the same reference', function () {
    Log::spy();
    $visitor = visitorReadyToBook($this);
    ($this->failingScheduler)();

    VisitorSite::say($this, $this->company['host'], $visitor['token'], 'sim');

    $conversation = $visitor['conversation']->fresh();
    expect($conversation->status)->toBe(ConversationStatus::AwaitingHuman)
        ->and($conversation->handoff_reason)->toContain('Falha técnica ao concluir o agendamento')
        ->and($conversation->handoff_reason)->toContain('nenhum horário foi reservado');

    preg_match('/ref\. ([A-Z0-9]{8})/', (string) $conversation->handoff_reason, $match);
    expect($match)->toHaveCount(2);

    Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($match) {
        return $message === 'assistant.booking_failed'
            && $context['reference'] === $match[1]
            && $context['error'] === QueryException::class
            && ! array_key_exists('message', $context)
            && ! array_key_exists('sql', $context)
            && ! array_key_exists('bindings', $context);
    })->once();
});

test('the request history shows the real result and never a booking', function () {
    $visitor = visitorReadyToBook($this);
    ($this->failingScheduler)();

    VisitorSite::say($this, $this->company['host'], $visitor['token'], 'sim');

    $request = ServiceRequest::query()->where('tenant_id', $this->company['tenant']->id)->sole();
    $states = ServiceRequestEvent::query()->where('service_request_id', $request->getKey())->pluck('to_status')->all();

    expect($states)->toContain(RequestStatus::Triaged->value)
        ->and($states)->not->toContain(RequestStatus::Scheduled->value)
        ->and(ServiceRequestEvent::query()->where('service_request_id', $request->getKey())->where('note', 'like', 'Agendado%')->exists())->toBeFalse();
});

test('after the failure the operator can retry, and the retry books exactly once', function () {
    $visitor = visitorReadyToBook($this);
    ($this->failingScheduler)();
    VisitorSite::say($this, $this->company['host'], $visitor['token'], 'sim');

    // The failure is gone: the operator retries with the real scheduler, on the preserved request.
    app()->forgetInstance(AppointmentScheduler::class);
    $request = ServiceRequest::query()->where('tenant_id', $this->company['tenant']->id)->sole();
    $slot = CarbonImmutable::now('America/Sao_Paulo')->addDay()->setTime(10, 0)->utc();

    $appointment = app(AppointmentScheduler::class)->schedule($request, $this->company['professional'], $slot, $this->company['owner']);

    expect($appointment->status->value)->toBe('scheduled')
        ->and($request->fresh()->status)->toBe(RequestStatus::Scheduled);

    // A repeated attempt on the same request is refused: no second appointment, no duplicated history.
    expect(fn () => app(AppointmentScheduler::class)->schedule($request->fresh(), $this->company['professional'], $slot, $this->company['owner']))
        ->toThrow(ValidationException::class);

    expect(Appointment::query()->where('service_request_id', $request->getKey())->count())->toBe(1)
        ->and(Appointment::query()->count())->toBe(1);
});

test('a visitor retry of the same confirmation does not book a second time after the failure', function () {
    $visitor = visitorReadyToBook($this);
    ($this->failingScheduler)();
    VisitorSite::say($this, $this->company['host'], $visitor['token'], 'sim');

    VisitorSite::say($this, $this->company['host'], $visitor['token'], 'sim');

    expect(Appointment::query()->count())->toBe(0)
        ->and(ServiceRequest::query()->where('tenant_id', $this->company['tenant']->id)->count())->toBe(1);
});
