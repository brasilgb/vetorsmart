<?php

namespace App\Services\Operations;

use App\Enums\AppointmentStatus;
use App\Enums\ConversationStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\AssistantConversation;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The work queues of a company for its dashboard: what needs someone now, most urgent first. Each queue reads the
 * real records of the company with one count and one short list (relations eager loaded), and states the actions the
 * signed-in member may take on each item, decided by the existing policies. The actions themselves are the existing
 * routes and services, which keep validating the status of the request: nothing here changes a record.
 */
class WorkCenter
{
    /** Items listed per queue on the dashboard; the full list is one click away. */
    public const int ITEMS = 5;

    /** Days ahead covered by "Próximos agendamentos". */
    public const int UPCOMING_DAYS = 7;

    /** Days back of the summary of completed services. */
    public const int SUMMARY_DAYS = 30;

    /**
     * @return array{queues: list<array<string, mixed>>, today: array<string, mixed>, summary: array<string, int>}
     */
    public function for(Tenant $tenant, User $user): array
    {
        $tenantId = (int) $tenant->getKey();
        $requests = fn (): Builder => ServiceRequest::query()->forTenant($tenantId);
        $localToday = CarbonImmutable::now($tenant->timezone)->startOfDay();
        $todayStart = $localToday->utc();
        $todayEnd = $localToday->addDay()->utc();
        $canHandleAssistant = Gate::forUser($user)->allows('handleAssistant', $tenant);

        $queues = [
            $this->requestQueue('triage', 'Novas aguardando triagem', 'Defina o serviço e a prioridade para seguir com o atendimento.', 'attention',
                $requests()->where('status', RequestStatus::Requested->value)->oldest('id'), $tenant, $user, route('tenant.requests.index', ['status' => RequestStatus::Requested->value], false)),
            $this->conversationQueue($tenantId, $canHandleAssistant),
            $this->appointmentQueue('late', 'Visitas atrasadas', 'O horário passou e o atendimento não foi iniciado nem encerrado.', 'warning',
                Appointment::query()->forTenant($tenantId)->where('status', AppointmentStatus::Scheduled->value)->where('ends_at', '<', now())
                    ->whereHas('serviceRequest', fn (Builder $request) => $request->where('status', RequestStatus::Scheduled->value))->orderBy('starts_at'),
                $tenant, $user, route('tenant.requests.index', ['status' => RequestStatus::Scheduled->value], false)),
            $this->requestQueue('schedule', 'Triadas aguardando agendamento', 'Escolha profissional e horário livre.', 'attention',
                $requests()->where('status', RequestStatus::Triaged->value)->whereNotNull('service_id')->oldest('id'), $tenant, $user, route('tenant.requests.index', ['status' => RequestStatus::Triaged->value], false)),
            $this->requestQueue('in_progress', 'Em andamento', 'Conclua informando o valor realizado, ou registre se não foi realizado.', 'info',
                $requests()->where('status', RequestStatus::InProgress->value)->oldest('id'), $tenant, $user, route('tenant.requests.index', ['status' => RequestStatus::InProgress->value], false)),
            $this->requestQueue('stalled', 'Sem movimentação recente', 'Em aberto há mais de '.ServiceRequest::STALE_AFTER_DAYS.' dias sem nenhum registro no histórico.', 'warning',
                $requests()->stalled()->oldest('id'), $tenant, $user, route('tenant.requests.index', ['parada' => 1], false)),
            $this->requestQueue('amount', 'Concluídos sem valor registrado', 'Informe o valor realizado para a receita ficar completa.', 'info',
                $requests()->where('status', RequestStatus::Completed->value)->whereNull('realized_amount_cents')->latest('closed_at'), $tenant, $user, route('tenant.requests.index', ['status' => RequestStatus::Completed->value, 'sem_valor' => 1], false)),
        ];

        $appointments = fn (): Builder => Appointment::query()->forTenant($tenantId)->where('status', AppointmentStatus::Scheduled->value);

        return [
            // Only the queues with something to do, in the order above (most urgent first).
            'queues' => array_values(array_filter($queues, fn (array $queue): bool => $queue['count'] > 0)),
            'today' => $this->appointmentQueue('today', 'Agenda de hoje', 'Visitas marcadas para hoje.', 'info',
                $appointments()->where('starts_at', '>=', $todayStart)->where('starts_at', '<', $todayEnd)->orderBy('starts_at'),
                $tenant, $user, route('tenant.agenda.index', ['view' => 'day'], false), 20),
            'upcoming' => $this->appointmentQueue('upcoming', 'Próximos agendamentos', 'Nos próximos '.self::UPCOMING_DAYS.' dias, a partir de amanhã.', 'info',
                $appointments()->where('starts_at', '>=', $todayEnd)->where('starts_at', '<', $localToday->addDays(self::UPCOMING_DAYS + 1)->utc())->orderBy('starts_at'),
                $tenant, $user, route('tenant.agenda.index', ['view' => 'week'], false), startable: false),
            'summary' => [
                'completed' => $requests()->where('status', RequestStatus::Completed->value)->where('closed_at', '>=', now()->subDays(self::SUMMARY_DAYS))->count(),
                'completed_today' => $requests()->where('status', RequestStatus::Completed->value)->whereBetween('closed_at', [$todayStart, $todayEnd])->count(),
                'cancelled' => $requests()->whereIn('status', [RequestStatus::Cancelled->value, RequestStatus::NotPerformed->value])->where('closed_at', '>=', now()->subDays(self::SUMMARY_DAYS))->count(),
                'open' => $requests()->whereIn('status', [RequestStatus::Requested->value, RequestStatus::Triaged->value, RequestStatus::Scheduled->value, RequestStatus::InProgress->value])->count(),
            ],
        ];
    }

    /**
     * @param  Builder<ServiceRequest>  $query
     * @return array<string, mixed>
     */
    private function requestQueue(string $key, string $title, string $hint, string $tone, Builder $query, Tenant $tenant, User $user, string $href): array
    {
        $count = (clone $query)->count();
        $items = $count === 0 ? [] : $query->with(['customer:id,name', 'service:id,name', 'assignee:id,name'])->limit(self::ITEMS)->get()
            ->map(fn (ServiceRequest $request): array => [
                ...$this->requestItem($request, $tenant),
                'actions' => $this->requestActions($request, $user),
            ])->values()->all();

        return compact('key', 'title', 'hint', 'tone', 'count', 'items', 'href');
    }

    /**
     * @param  Builder<Appointment>  $query
     * @return array<string, mixed>
     */
    private function appointmentQueue(string $key, string $title, string $hint, string $tone, Builder $query, Tenant $tenant, User $user, string $href, int $limit = self::ITEMS, bool $startable = true): array
    {
        $count = (clone $query)->count();
        $items = $count === 0 ? [] : $query->with(['professional:id,name', 'serviceRequest.customer:id,name', 'serviceRequest.service:id,name', 'serviceRequest.assignee:id,name'])->limit($limit)->get()
            ->filter(fn (Appointment $appointment): bool => $appointment->serviceRequest !== null)
            ->map(function (Appointment $appointment) use ($tenant, $user, $startable): array {
                $request = $appointment->serviceRequest;
                $starts = $tenant->toLocal($appointment->starts_at);

                return [
                    ...$this->requestItem($request, $tenant),
                    'appointment_id' => $appointment->id,
                    'when' => $starts->isSameDay(CarbonImmutable::now($tenant->timezone)) ? 'Hoje, '.$starts->format('H:i') : $starts->format('d/m H:i'),
                    'ends' => $tenant->toLocal($appointment->ends_at)->format('H:i'),
                    'professional' => $appointment->professional?->name,
                    'agenda' => route('tenant.agenda.index', ['view' => 'day', 'date' => $starts->format('Y-m-d')], false),
                    // A visit of another day is not started from here: only today's and the overdue ones.
                    'actions' => $startable ? $this->requestActions($request, $user) : array_diff_key($this->requestActions($request, $user), ['start' => true]),
                ];
            })->values()->all();

        return compact('key', 'title', 'hint', 'tone', 'count', 'items', 'href');
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationQueue(int $tenantId, bool $canHandle): array
    {
        $query = AssistantConversation::query()->forTenant($tenantId)->where('status', ConversationStatus::AwaitingHuman->value)->oldest('last_activity_at');
        $count = (clone $query)->count();
        $items = $count === 0 ? [] : $query->with('customer:id,name')->limit(self::ITEMS)->get()
            ->map(fn (AssistantConversation $conversation): array => [
                'id' => $conversation->id,
                'title' => $conversation->customer->name ?? 'Visitante do site',
                'detail' => $conversation->handoff_reason ?: 'Aguardando uma pessoa da equipe.',
                'since' => $conversation->last_activity_at?->diffForHumans(),
                'href' => route('tenant.assistant.index', ['conversation' => $conversation->id], false),
                'assume' => $canHandle ? route('tenant.assistant.assume', ['conversation' => $conversation->id], false) : null,
            ])->values()->all();

        return [
            'key' => 'human', 'title' => 'Atendimento IA aguardando a equipe', 'hint' => 'O assistente passou a conversa para uma pessoa.', 'tone' => 'attention',
            'count' => $count, 'items' => $items, 'href' => route('tenant.assistant.index', [], false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestItem(ServiceRequest $request, Tenant $tenant): array
    {
        return [
            'id' => $request->id,
            'title' => '#'.$request->id.' · '.($request->customer->name ?? 'Cliente'),
            'detail' => $request->service->name ?? 'Serviço a definir',
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'assignee' => $request->assignee?->name,
            'since' => 'desde '.$tenant->toLocal($request->created_at)->format('d/m H:i'),
            'href' => route('tenant.requests.show', ['serviceRequest' => $request->id], false),
        ];
    }

    /**
     * The quick actions that make sense for the current status and that the member may take (existing policies).
     * The server validates the transition again when the action is sent.
     *
     * @return array<string, string>
     */
    private function requestActions(ServiceRequest $request, User $user): array
    {
        $gate = Gate::forUser($user);

        if (! $gate->allows('manage', $request)) {
            return [];
        }

        $id = ['serviceRequest' => $request->id];
        $actions = match ($request->status) {
            RequestStatus::Requested => ['triage' => route('tenant.requests.show', $id, false).'#triagem'],
            RequestStatus::Triaged => $request->service_id === null ? [] : ['schedule' => route('tenant.requests.show', $id, false).'#agendamento'],
            RequestStatus::Scheduled => ['start' => route('tenant.requests.start', $id, false)],
            RequestStatus::InProgress => ['complete' => route('tenant.requests.complete', $id, false)],
            default => [],
        };

        if ($request->status === RequestStatus::Completed && $request->realized_amount_cents === null && $gate->allows('recordAmount', $request)) {
            $actions['amount'] = route('tenant.requests.amount', $id, false);
        }

        // Offered only where the status allows it (a request in progress is completed or marked not performed instead).
        if ($request->status->canTransitionTo(RequestStatus::Cancelled)) {
            $actions['cancel'] = route('tenant.requests.cancel', $id, false);
        }

        return $actions;
    }
}
