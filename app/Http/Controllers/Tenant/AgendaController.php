<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AppointmentStatus;
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
use Illuminate\Support\Facades\DB;
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
        // A read-only list: one joined query returns the names shown, instead of hydrating a model and two relations per row.
        // The company is stated in the query itself, because this read does not go through the model scope.
        $appointments = DB::table('appointments as a')
            ->join('service_requests as r', 'r.id', '=', 'a.service_request_id')
            ->leftJoin('professionals as p', 'p.id', '=', 'a.professional_id')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->leftJoin('services as sv', 'sv.id', '=', 'r.service_id')
            ->where('a.tenant_id', $tenant->getKey())
            ->where('a.starts_at', '<', $to->utc())
            ->where('a.ends_at', '>', $from->utc())
            ->orderBy('a.starts_at')
            ->get(['a.id', 'a.service_request_id', 'a.starts_at', 'a.ends_at', 'a.status', 'p.name as professional', 'c.name as customer', 'sv.name as service'])
            ->map(function (object $row) use ($tenant): array {
                $status = AppointmentStatus::from($row->status);
                // The local instants are converted once per row, not once per field.
                $startsLocal = $tenant->toLocal(CarbonImmutable::parse($row->starts_at, 'UTC'));
                $endsLocal = $tenant->toLocal(CarbonImmutable::parse($row->ends_at, 'UTC'));

                return [
                    'id' => $row->id,
                    'request_id' => $row->service_request_id,
                    'date' => $startsLocal->format('Y-m-d'),
                    'time' => $startsLocal->format('H:i'),
                    'ends' => $endsLocal->format('H:i'),
                    'professional' => $row->professional,
                    'customer' => $row->customer,
                    'service' => $row->service,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'can_change' => $status->isActive(),
                    'starts_at' => $startsLocal->format('Y-m-d H:i'),
                ];
            })
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
                    'label' => '#'.$serviceRequest->id.' · '.($serviceRequest->customer->name ?? '—').' · '.($serviceRequest->service->name ?? '—'),
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
