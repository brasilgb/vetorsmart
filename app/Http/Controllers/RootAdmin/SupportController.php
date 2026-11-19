<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\ChargeStatus;
use App\Enums\DomainStatus;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RootAdmin\StoreSupportTicketRequest;
use App\Http\Requests\RootAdmin\UpdateSupportTicketRequest;
use App\Models\AssistantUsage;
use App\Models\BillingCharge;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use App\Support\CsvExport;
use App\Support\PlatformTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Central de Suporte, platform side (RootAdmin only): requests of every company and of the platform, with filters,
 * indicators (open, waiting for the company, overdue), person responsible, due date set on the request, replies seen
 * by the company, internal notes, controlled status changes, closing and reopening. Every change goes through
 * SupportTicketService (append-only history and audit). Companies with problems in real data are listed as signals.
 */
class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = $this->filtered($request)->with(['tenant:id,name', 'assignee:id,name'])->paginate(20)->withQueryString();
        $open = SupportTicket::query()->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value]);

        return Inertia::render('rootadmin/support/index', [
            'filters' => $this->filterValues($request),
            'statuses' => $this->options(SupportTicketStatus::cases()),
            'priorities' => $this->options(SupportTicketPriority::cases()),
            'categories' => SupportTicketCategory::options(),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name'])->map(fn (Tenant $tenant): array => ['id' => $tenant->id, 'name' => $tenant->name])->all(),
            'assignees' => $this->assignees(),
            'indicators' => [
                'open' => (clone $open)->count(),
                'waiting' => SupportTicket::query()->where('status', SupportTicketStatus::Waiting->value)->count(),
                'overdue' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
            ],
            'tickets' => $tickets->through(fn (SupportTicket $ticket): array => $this->row($ticket)),
            'signals' => $this->signals(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return CsvExport::download('chamados-'.now()->format('Y-m-d').'.csv', [
            'Número', 'Empresa', 'Assunto', 'Categoria', 'Situação', 'Prioridade', 'Origem', 'Responsável', 'Prazo', 'Atrasado', 'Aberto em', 'Última atualização', 'Finalizado em',
        ], function (callable $write) use ($request): void {
            $this->filtered($request)->with(['tenant:id,name', 'assignee:id,name'])->chunk(200, function (Collection $tickets) use ($write): void {
                foreach ($tickets as $ticket) {
                    $write([
                        $ticket->id, $ticket->tenant->name ?? 'Plataforma', $ticket->subject, $ticket->category->label(), $ticket->status->label(), $ticket->priority->label(),
                        $ticket->opened_via === 'tenant' ? 'Empresa' : 'Plataforma', $ticket->assignee->name ?? '', PlatformTime::dateTime($ticket->due_at) ?? '',
                        $ticket->isOverdue(), PlatformTime::dateTime($ticket->created_at), PlatformTime::dateTime($ticket->last_activity_at), PlatformTime::dateTime($ticket->closed_at) ?? '',
                    ]);
                }
            });
        });
    }

    public function show(SupportTicket $ticket): Response
    {
        $ticket->load(['tenant:id,name', 'opener:id,name', 'assignee:id,name']);
        $names = User::query()->whereIn('id', $ticket->events()->where('type', 'assigned')->get(['from_value', 'to_value'])->flatMap(fn (SupportTicketEvent $event): array => [$event->from_value, $event->to_value])->filter())->pluck('name', 'id');

        return Inertia::render('rootadmin/support/show', [
            'ticket' => [
                ...$this->row($ticket),
                'description' => $ticket->description,
                'opened_by' => $ticket->opener?->name,
                'closed_at' => PlatformTime::dateTime($ticket->closed_at),
                'assigned_to' => $ticket->assigned_to,
                'due_at_input' => $ticket->due_at === null ? '' : PlatformTime::toDisplay($ticket->due_at)->format('Y-m-d\TH:i'),
            ],
            'events' => $ticket->events()->with('user:id,name')->latest('id')->limit(200)->get()->map(fn (SupportTicketEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'from' => $this->valueLabel($event->type, $event->from_value, $names),
                'to' => $this->valueLabel($event->type, $event->to_value, $names),
                'note' => $event->note,
                'internal' => $event->is_internal,
                'user' => $event->user?->name,
                'at' => PlatformTime::dateTime($event->created_at),
            ])->all(),
            'statuses' => $this->options(SupportTicketStatus::cases()),
            'priorities' => $this->options(SupportTicketPriority::cases()),
            'assignees' => $this->assignees(),
        ]);
    }

    public function store(StoreSupportTicketRequest $request, SupportTicketService $support): RedirectResponse
    {
        $data = $request->validated();
        $ticket = $support->open(
            isset($data['tenant_id']) ? Tenant::query()->whereKey((int) $data['tenant_id'])->first() : null,
            $data['subject'],
            $data['description'],
            SupportTicketCategory::tryFrom($data['category'] ?? '') ?? SupportTicketCategory::Other,
            SupportTicketPriority::from($data['priority']),
            'platform',
            $this->actor($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Chamado aberto.']);

        return to_route('rootadmin.support.show', $ticket);
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $ticket, SupportTicketService $support): RedirectResponse
    {
        $data = $request->validated();

        $support->platformUpdate($ticket, [
            'note' => $data['note'] ?? null,
            'internal' => $request->boolean('internal'),
            'status' => isset($data['status']) ? SupportTicketStatus::from($data['status']) : null,
            'priority' => isset($data['priority']) ? SupportTicketPriority::from($data['priority']) : null,
            'assign' => $request->has('assigned_to'),
            'assigned_to' => filled($data['assigned_to'] ?? null) ? User::query()->whereKey((int) $data['assigned_to'])->first() : null,
            'set_due' => $request->has('due_at'),
            'due_at' => filled($data['due_at'] ?? null) ? PlatformTime::fromLocal((string) $data['due_at']) : null,
        ], $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Chamado atualizado.']);

        return to_route('rootadmin.support.show', $ticket);
    }

    public function reopen(Request $request, SupportTicket $ticket, SupportTicketService $support): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], ['reason.*' => 'Informe o motivo da reabertura (5 a 500 caracteres).']);
        $support->reopen($ticket, $this->actor($request), $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Chamado reaberto.']);

        return to_route('rootadmin.support.show', $ticket);
    }

    /**
     * @return Builder<SupportTicket>
     */
    private function filtered(Request $request): Builder
    {
        $values = $this->filterValues($request);
        $status = SupportTicketStatus::tryFrom($values['status']);
        $priority = SupportTicketPriority::tryFrom($values['priority']);
        $category = SupportTicketCategory::tryFrom($values['category']);

        return SupportTicket::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($values['status'] === '' || $values['status'] === 'atrasados', fn ($query) => $query->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value]))
            ->when($values['status'] === 'atrasados', fn ($query) => $query->whereNotNull('due_at')->where('due_at', '<', now()))
            ->when($priority !== null, fn ($query) => $query->where('priority', $priority->value))
            ->when($category !== null, fn ($query) => $query->where('category', $category->value))
            ->when($values['tenant_id'] !== '', fn ($query) => $query->where('tenant_id', (int) $values['tenant_id']))
            ->when($values['assigned_to'] === 'ninguem', fn ($query) => $query->whereNull('assigned_to'))
            ->when(ctype_digit($values['assigned_to']), fn ($query) => $query->where('assigned_to', (int) $values['assigned_to']))
            ->when($values['q'] !== '', fn ($query) => $query->where('subject', 'like', '%'.addcslashes($values['q'], '%_\\').'%'))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');
    }

    /**
     * @return array{status: string, priority: string, category: string, tenant_id: string, assigned_to: string, q: string}
     */
    private function filterValues(Request $request): array
    {
        return [
            'status' => $request->string('status')->value(),
            'priority' => $request->string('priority')->value(),
            'category' => $request->string('category')->value(),
            'tenant_id' => ctype_digit($request->string('tenant_id')->value()) ? $request->string('tenant_id')->value() : '',
            'assigned_to' => $request->string('assigned_to')->value(),
            'q' => $request->string('q')->trim()->value(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'tenant' => $ticket->tenant?->name,
            'tenant_id' => $ticket->tenant_id,
            'category_label' => $ticket->category->label(),
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'priority' => $ticket->priority->value,
            'priority_label' => $ticket->priority->label(),
            'opened_via' => $ticket->opened_via,
            'assignee' => $ticket->assignee?->name,
            'due_at' => PlatformTime::dateTime($ticket->due_at),
            'overdue' => $ticket->isOverdue(),
            'is_final' => $ticket->status->isFinal(),
            'opened_at' => PlatformTime::dateTime($ticket->created_at),
            'last_activity_at' => PlatformTime::dateTime($ticket->last_activity_at),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function assignees(): array
    {
        return array_values(User::query()->where('is_root_admin', true)->where('status', UserStatus::Active->value)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->all());
    }

    /**
     * @param  list<SupportTicketStatus>|list<SupportTicketPriority>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (SupportTicketStatus|SupportTicketPriority $item): array => ['value' => $item->value, 'label' => $item->label()], $cases);
    }

    /**
     * @param  Collection<int, string>  $names
     */
    private function valueLabel(string $type, ?string $value, Collection $names): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'priority' => SupportTicketPriority::tryFrom($value)?->label(),
            'assigned' => $names->get((int) $value),
            'due' => PlatformTime::dateTime(Carbon::parse($value)),
            default => SupportTicketStatus::tryFrom($value)?->label(),
        } ?? $value;
    }

    /**
     * Companies with problems visible in real data. Homologation companies are left out.
     *
     * @return array<string, array<int, array{tenant_id: int, tenant: string, detail: string}>>
     */
    private function signals(): array
    {
        $realTenants = Tenant::query()->where('is_homolog', false)->select('id');

        return [
            'suspended' => Tenant::query()->where('is_homolog', false)->where('status', '!=', TenantStatus::Active->value)->orderBy('name')->limit(10)->get(['id', 'name', 'status'])
                ->map(fn (Tenant $tenant): array => ['tenant_id' => $tenant->id, 'tenant' => $tenant->name, 'detail' => $tenant->status === TenantStatus::Suspended ? 'Suspensa pela administração' : 'Cancelada'])->values()->all(),
            'domains' => TenantDomain::query()->with('tenant:id,name')->whereIn('tenant_id', $realTenants)->whereNull('retired_at')->where('status', DomainStatus::Failed->value)->limit(10)->get()
                ->map(fn (TenantDomain $domain): array => ['tenant_id' => $domain->tenant_id, 'tenant' => $domain->tenant->name, 'detail' => "Verificação falhou: {$domain->hostname}"])->values()->all(),
            'charges' => BillingCharge::query()->with('tenant:id,name')->whereIn('tenant_id', $realTenants)->where('status', ChargeStatus::Pending->value)->where('issue_attempts', '>', 0)->limit(10)->get()
                ->map(fn (BillingCharge $charge): array => ['tenant_id' => $charge->tenant_id, 'tenant' => $charge->tenant->name, 'detail' => "Cobrança #{$charge->id} sem emissão após {$charge->issue_attempts} tentativa(s)"])->values()->all(),
            'assistant' => AssistantUsage::query()->with('tenant:id,name')->where('usage_date', '>=', now()->startOfMonth()->toDateString())->where('provider_failures', '>', 0)
                ->whereIn('tenant_id', Tenant::query()->where('is_homolog', false)->select('id'))
                ->groupBy('tenant_id')->selectRaw('tenant_id, SUM(provider_failures) AS failures')->orderByDesc('failures')->limit(10)->get()
                ->map(fn (AssistantUsage $row): array => ['tenant_id' => $row->tenant_id, 'tenant' => (string) $row->tenant?->name, 'detail' => $row->getAttribute('failures').' falha(s) do provedor de IA no mês'])->values()->all(),
        ];
    }
}
