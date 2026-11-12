<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Operations\ReasonRequest;
use App\Http\Requests\Tenant\Operations\RescheduleAppointmentRequest;
use App\Http\Requests\Tenant\Operations\StoreProfessionalBlockRequest;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\ProfessionalBlock;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Services\AppointmentScheduler;
use App\Services\AuditLogger;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    /**
     * Agenda in day, week or month view. The range is computed here, from the date and view requested.
     */
    public function index(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', Appointment::class);

        $tenant = $context->requireTenant();
        $view = in_array($request->string('view')->value(), ['day', 'week', 'month'], true) ? $request->string('view')->value() : 'week';
        $date = $this->parseDate($request->string('date')->value(), $tenant);
        [$from, $to] = $this->range($view, $date);

        // Every status is listed so that completed, not performed and cancelled visits keep their history on the calendar.
        // Only a scheduled appointment can still be rescheduled or cancelled (see can_change).
        $appointments = Appointment::query()
            ->forTenant($tenant)
            ->where('starts_at', '<', $to->utc())
            ->where('ends_at', '>', $from->utc())
            ->with(['professional:id,name', 'serviceRequest.customer:id,name', 'serviceRequest.service:id,name'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'request_id' => $appointment->service_request_id,
                'date' => $tenant->toLocal($appointment->starts_at)->format('Y-m-d'),
                'time' => $tenant->toLocal($appointment->starts_at)->format('H:i'),
                'ends' => $tenant->toLocal($appointment->ends_at)->format('H:i'),
                'professional' => $appointment->professional?->name,
                'customer' => $appointment->serviceRequest?->customer?->name,
                'service' => $appointment->serviceRequest?->service?->name,
                'status' => $appointment->status->value,
                'status_label' => $appointment->status->label(),
                'can_change' => $appointment->status->isActive(),
                'starts_at' => $tenant->toLocal($appointment->starts_at)->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();

        $blocks = ProfessionalBlock::query()
            ->forTenant($tenant)
            ->where('starts_at', '<', $to->utc())
            ->where('ends_at', '>', $from->utc())
            ->with('professional:id,name')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ProfessionalBlock $block): array => [
                'id' => $block->id,
                'date' => $tenant->toLocal($block->starts_at)->format('Y-m-d'),
                'time' => $tenant->toLocal($block->starts_at)->format('H:i'),
                'ends' => $tenant->toLocal($block->ends_at)->format('H:i'),
                'professional' => $block->professional?->name,
                'reason' => $block->reason,
            ])
            ->values()
            ->all();

        return Inertia::render('tenant/agenda/index', [
            'view' => $view,
            'date' => $date->format('Y-m-d'),
            'range' => ['from' => $from->format('Y-m-d'), 'to' => $to->subDay()->format('Y-m-d')],
            'appointments' => $appointments,
            'blocks' => $blocks,
            'professionals' => Professional::query()->forTenant($tenant)->where('active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'openRequests' => ServiceRequest::query()
                ->forTenant($tenant)
                ->where('status', RequestStatus::Triaged->value)
                ->whereNotNull('service_id')
                ->with(['customer:id,name', 'service:id,name'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (ServiceRequest $serviceRequest): array => [
                    'id' => $serviceRequest->id,
                    'label' => '#'.$serviceRequest->id.' · '.($serviceRequest->customer?->name ?? '—').' · '.($serviceRequest->service?->name ?? '—'),
                    'service_id' => $serviceRequest->service_id,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Free start times of a professional for a triaged request on a given day, as JSON.
     */
    public function slots(Request $request, TenantContext $context, AppointmentScheduler $scheduler): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);

        $tenant = $context->requireTenant();

        $serviceRequest = ServiceRequest::query()->forTenant($tenant)->whereKey($request->integer('request_id'))->firstOrFail();
        $professional = Professional::query()->forTenant($tenant)->whereKey($request->integer('professional_id'))->firstOrFail();
        $service = Service::query()->forTenant($tenant)->whereKey($serviceRequest->service_id)->firstOrFail();

        $day = $this->parseDate($request->string('date')->value(), $tenant);

        return response()->json([
            'slots' => $scheduler->availableSlots($professional, $service, $day),
        ]);
    }

    /**
     * A JSON caller gets a 200 with the new time. A redirect would be followed with the PATCH method and fail,
     * because the agenda page only accepts GET.
     */
    public function reschedule(RescheduleAppointmentRequest $request, TenantContext $context, AppointmentScheduler $scheduler, int $appointment): RedirectResponse|JsonResponse
    {
        $record = $this->findAppointment($context, $appointment);

        $this->authorize('manage', $record);

        $updated = $scheduler->reschedule($record, $request->startsAt($context->requireTenant()), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Atendimento remarcado.']);

        if ($request->expectsJson()) {
            return response()->json([
                'starts_at' => $context->requireTenant()->toLocal($updated->starts_at)->format('Y-m-d H:i'),
            ]);
        }

        return back();
    }

    public function cancel(ReasonRequest $request, TenantContext $context, AppointmentScheduler $scheduler, int $appointment): RedirectResponse
    {
        $record = $this->findAppointment($context, $appointment);

        $this->authorize('manage', $record);

        $scheduler->cancel($record, $request->string('reason')->value(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento cancelado. A solicitação voltou para a triagem.']);

        return back();
    }

    public function storeBlock(StoreProfessionalBlockRequest $request, TenantContext $context, AppointmentScheduler $scheduler): RedirectResponse
    {
        $this->authorize('create', Appointment::class);

        $tenant = $context->requireTenant();
        $professional = Professional::query()->forTenant($tenant)->whereKey($request->integer('professional_id'))->firstOrFail();

        $scheduler->block($tenant, $professional, $request->startsAt($tenant), $request->endsAt($tenant), $request->input('reason'), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Período bloqueado.']);

        return back();
    }

    public function destroyBlock(Request $request, TenantContext $context, AuditLogger $auditLogger, int $block): RedirectResponse
    {
        $record = ProfessionalBlock::query()->forTenant($context->requireTenant())->whereKey($block)->firstOrFail();

        $this->authorize('deleteBlock', $record);

        $auditLogger->record('professional_block.removed', $context->requireTenant(), $record, [], $this->actor($request));
        $record->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueio removido.']);

        return back();
    }

    private function findAppointment(TenantContext $context, int $appointmentId): Appointment
    {
        return Appointment::query()->forTenant($context->requireTenant())->whereKey($appointmentId)->firstOrFail();
    }

    /**
     * A local day in the tenant's timezone. A missing or malformed date falls back to today there,
     * so the menu link (without parameters) works.
     */
    private function parseDate(string $value, Tenant $tenant): CarbonImmutable
    {
        if ($value !== '') {
            try {
                return $tenant->startOfLocalDay($value);
            } catch (\Throwable) {
                // Malformed input: fall back to today below.
            }
        }

        return CarbonImmutable::now($tenant->timezone)->startOfDay();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} Start (inclusive) and end (exclusive).
     */
    private function range(string $view, CarbonImmutable $date): array
    {
        return match ($view) {
            'day' => [$date, $date->addDay()],
            'month' => [$date->startOfMonth(), $date->endOfMonth()->addDay()->startOfDay()],
            default => [$date->startOfWeek(CarbonImmutable::MONDAY), $date->startOfWeek(CarbonImmutable::MONDAY)->addWeek()],
        };
    }
}
