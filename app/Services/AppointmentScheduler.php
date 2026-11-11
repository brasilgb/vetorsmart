<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\ProfessionalBlock;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agenda rules: a professional can only take an appointment inside a working window, outside
 * blocks and other appointments, and only for a service they are enabled to perform.
 *
 * Every write locks the professional row first, so two operators booking the same professional
 * are serialised and the second one sees the first booking.
 */
class AppointmentScheduler
{
    /** Interval between suggested start times, in minutes. */
    private const int SLOT_STEP_MINUTES = 30;

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Book a triaged request with a professional at a given start time.
     *
     * @throws ValidationException
     */
    public function schedule(ServiceRequest $request, Professional $professional, CarbonImmutable $startsAt, ?User $actor): Appointment
    {
        return DB::transaction(function () use ($request, $professional, $startsAt, $actor): Appointment {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status !== RequestStatus::Triaged || $request->service_id === null) {
                throw ValidationException::withMessages([
                    'status' => ['Só é possível agendar uma solicitação triada, com serviço definido.'],
                ]);
            }

            $service = Service::query()->forTenant($request->tenant_id)->whereKey($request->service_id)->firstOrFail();

            if (! $service->isAvailableForScheduling()) {
                throw ValidationException::withMessages([
                    'service_id' => ['Este serviço foi desativado. Reclassifique a solicitação antes de agendar.'],
                ]);
            }

            $professional = Professional::query()->forTenant($request->tenant_id)->whereKey($professional->getKey())->lockForUpdate()->firstOrFail();
            $tenant = Tenant::query()->whereKey($request->tenant_id)->firstOrFail();

            $endsAt = $this->endFor($service, $startsAt);
            $this->ensureBookable($tenant, $professional, $service, $startsAt, $endsAt, null);

            $appointment = Appointment::create([
                'tenant_id' => $request->tenant_id,
                'service_request_id' => $request->getKey(),
                'professional_id' => $professional->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::Scheduled,
                'origin' => $request->origin->value,
                'created_by_user_id' => $actor?->getKey(),
            ]);

            $request->professional_id = $professional->getKey();
            $request->save();
            $request->transitionTo(RequestStatus::Scheduled, $actor, 'Agendado para '.$startsAt->format('d/m/Y H:i').'.');

            $this->auditLogger->record('appointment.created', $request->tenant, $appointment, [
                'service_request_id' => $request->getKey(),
                'professional_id' => $professional->getKey(),
            ], $actor);

            return $appointment;
        });
    }

    /**
     * Move an active appointment. The appointment itself is excluded from the conflict check.
     *
     * @throws ValidationException
     */
    public function reschedule(Appointment $appointment, CarbonImmutable $startsAt, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $startsAt, $actor): Appointment {
            $appointment = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            if (! $appointment->status->isActive()) {
                throw ValidationException::withMessages(['status' => ['Somente agendamentos ativos podem ser remarcados.']]);
            }

            $professional = Professional::query()->forTenant($appointment->tenant_id)->whereKey($appointment->professional_id)->lockForUpdate()->firstOrFail();
            $service = Service::query()->forTenant($appointment->tenant_id)->whereKey($appointment->serviceRequest->service_id)->firstOrFail();
            $tenant = Tenant::query()->whereKey($appointment->tenant_id)->firstOrFail();

            $endsAt = $this->endFor($service, $startsAt);
            $this->ensureBookable($tenant, $professional, $service, $startsAt, $endsAt, $appointment->getKey());

            $previousStart = $appointment->starts_at;
            $appointment->forceFill(['starts_at' => $startsAt, 'ends_at' => $endsAt])->save();

            $this->auditLogger->record('appointment.rescheduled', $appointment->tenant, $appointment, [
                'from' => $previousStart->toIso8601String(),
                'to' => $startsAt->toIso8601String(),
            ], $actor);

            return $appointment;
        });
    }

    /**
     * Cancel an active appointment. The request returns to triage so it can be booked again.
     */
    public function cancel(Appointment $appointment, string $reason, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $reason, $actor): Appointment {
            $appointment = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            if (! $appointment->status->isActive()) {
                throw ValidationException::withMessages(['status' => ['Este agendamento não está ativo.']]);
            }

            $appointment->forceFill([
                'status' => AppointmentStatus::Cancelled,
                'cancelling_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->getKey(),
            ])->save();

            $request = ServiceRequest::query()->whereKey($appointment->service_request_id)->lockForUpdate()->firstOrFail();
            $request->transitionTo(RequestStatus::Triaged, $actor, 'Agendamento cancelado por '.$actor->name.' em '.now()->format('d/m/Y H:i').': '.$reason);

            $this->auditLogger->record('appointment.cancelled', $appointment->tenant, $appointment, [
                'service_request_id' => $request->getKey(),
            ], $actor);

            return $appointment;
        });
    }

    /**
     * Block a period of a professional. Periods that already hold an active appointment are refused.
     *
     * @throws ValidationException
     */
    public function block(Tenant $tenant, Professional $professional, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $reason, User $actor): ProfessionalBlock
    {
        return DB::transaction(function () use ($tenant, $professional, $startsAt, $endsAt, $reason, $actor): ProfessionalBlock {
            $professional = Professional::query()->forTenant($tenant)->whereKey($professional->getKey())->lockForUpdate()->firstOrFail();

            if ($this->appointmentOverlaps($professional, $startsAt, $endsAt, null)) {
                throw ValidationException::withMessages(['starts_at' => ['Há agendamentos ativos nesse período.']]);
            }

            $block = ProfessionalBlock::create([
                'tenant_id' => $tenant->getKey(),
                'professional_id' => $professional->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $reason,
            ]);

            $this->auditLogger->record('professional_block.created', $tenant, $block, [], $actor);

            return $block;
        });
    }

    /**
     * Start times that can be booked for a service with a professional on a given day, in steps
     * of SLOT_STEP_MINUTES. Calculated on the server from windows, blocks and appointments.
     * The day must be a local day in the tenant's timezone (see Tenant::startOfLocalDay), so the
     * working windows and the returned HH:MM values are local times.
     *
     * @return list<string> Times as HH:MM.
     */
    public function availableSlots(Professional $professional, Service $service, CarbonImmutable $day): array
    {
        if ($service->duration_minutes === null || ! $professional->isAvailableForScheduling()) {
            return [];
        }

        $slots = [];
        $now = CarbonImmutable::now();

        $windows = $professional->availabilities()
            ->where('weekday', $day->dayOfWeek)
            ->orderBy('start_time')
            ->get();

        foreach ($windows as $window) {
            $cursor = $day->setTimeFromTimeString($window->start_time);
            $limit = $day->setTimeFromTimeString($window->end_time);

            while ($cursor->addMinutes($service->duration_minutes)->lessThanOrEqualTo($limit)) {
                $end = $cursor->addMinutes($service->duration_minutes);

                if ($cursor->greaterThan($now) && ! $this->hasConflict($professional, $cursor->utc(), $end->utc(), null)) {
                    $slots[] = $cursor->format('H:i');
                }

                $cursor = $cursor->addMinutes(self::SLOT_STEP_MINUTES);
            }
        }

        return array_values(array_unique($slots));
    }

    /**
     * @throws ValidationException
     */
    private function ensureBookable(Tenant $tenant, Professional $professional, Service $service, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $ignoreAppointmentId): void
    {
        if (! $professional->isAvailableForScheduling()) {
            throw ValidationException::withMessages(['professional_id' => ['Este profissional está inativo.']]);
        }

        $isLinked = $service->professionals()->whereKey($professional->getKey())->exists();

        if (! $isLinked) {
            throw ValidationException::withMessages(['professional_id' => ['Este profissional não executa o serviço escolhido.']]);
        }

        if (! $startsAt->isAfter(now())) {
            throw ValidationException::withMessages(['starts_at' => ['Escolha um horário futuro.']]);
        }

        if (! $tenant->toLocal($startsAt)->isSameDay($tenant->toLocal($endsAt))) {
            throw ValidationException::withMessages(['starts_at' => ['O atendimento precisa terminar no mesmo dia.']]);
        }

        if (! $this->fitsWorkingWindow($tenant, $professional, $startsAt, $endsAt)) {
            throw ValidationException::withMessages(['starts_at' => ['Horário fora da jornada do profissional.']]);
        }

        if ($this->hasConflict($professional, $startsAt, $endsAt, $ignoreAppointmentId)) {
            throw ValidationException::withMessages(['starts_at' => ['Este horário já está ocupado para o profissional.']]);
        }
    }

    private function endFor(Service $service, CarbonImmutable $startsAt): CarbonImmutable
    {
        if ($service->duration_minutes === null) {
            throw ValidationException::withMessages(['service_id' => ['Defina a duração do serviço antes de agendar.']]);
        }

        return $startsAt->addMinutes($service->duration_minutes);
    }

    /**
     * Working windows are stored as the tenant's local weekday and time, so the instant is read locally.
     */
    private function fitsWorkingWindow(Tenant $tenant, Professional $professional, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        $localStart = $tenant->toLocal($startsAt);
        $localEnd = $tenant->toLocal($endsAt);

        return $professional->availabilities()
            ->where('weekday', $localStart->dayOfWeek)
            ->where('start_time', '<=', $localStart->format('H:i:s'))
            ->where('end_time', '>=', $localEnd->format('H:i:s'))
            ->exists();
    }

    private function hasConflict(Professional $professional, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $ignoreAppointmentId): bool
    {
        return $this->appointmentOverlaps($professional, $startsAt, $endsAt, $ignoreAppointmentId)
            || ProfessionalBlock::query()
                ->where('professional_id', $professional->getKey())
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();
    }

    private function appointmentOverlaps(Professional $professional, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $ignoreAppointmentId): bool
    {
        return Appointment::query()
            ->where('professional_id', $professional->getKey())
            ->where('status', AppointmentStatus::Scheduled->value)
            ->when($ignoreAppointmentId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }
}
