<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\ChargeStatus;
use App\Enums\DomainStatus;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RootAdmin\StoreSupportTicketRequest;
use App\Http\Requests\RootAdmin\UpdateSupportTicketRequest;
use App\Models\AssistantUsage;
use App\Models\BillingCharge;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\AuditLogger;
use App\Support\PlatformTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suporte operacional of the platform team (RootAdmin only): companies showing problems in real data, and support
 * records with status, priority and an append-only follow-up history. Every change is audited. No chat, no external
 * channel: this is the internal base, not the future Central ABrasil Suporte.
 */
class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = SupportTicketStatus::tryFrom($request->string('status')->value());
        $priority = SupportTicketPriority::tryFrom($request->string('priority')->value());
        $term = $request->string('q')->trim()->value();
        $openOnly = $status === null && $request->string('status')->value() !== 'todos';

        $tickets = SupportTicket::query()
            ->with('tenant:id,name')
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($openOnly, fn ($query) => $query->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value]))
            ->when($priority !== null, fn ($query) => $query->where('priority', $priority->value))
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($term !== '', fn ($query) => $query->where('subject', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('last_activity_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('rootadmin/support/index', [
            'filters' => ['status' => $status->value ?? ($openOnly ? '' : 'todos'), 'priority' => $priority->value ?? '', 'q' => $term, 'tenant_id' => $request->string('tenant_id')->value()],
            'statuses' => array_map(fn (SupportTicketStatus $item): array => ['value' => $item->value, 'label' => $item->label()], SupportTicketStatus::cases()),
            'priorities' => array_map(fn (SupportTicketPriority $item): array => ['value' => $item->value, 'label' => $item->label()], SupportTicketPriority::cases()),
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name'])->map(fn (Tenant $tenant): array => ['id' => $tenant->id, 'name' => $tenant->name])->all(),
            'tickets' => $tickets->through(fn (SupportTicket $ticket): array => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'tenant' => $ticket->tenant?->name,
                'tenant_id' => $ticket->tenant_id,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'priority' => $ticket->priority->value,
                'priority_label' => $ticket->priority->label(),
                'opened_at' => PlatformTime::dateTime($ticket->created_at),
                'last_activity_at' => PlatformTime::dateTime($ticket->last_activity_at),
            ]),
            'signals' => $this->signals(),
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $ticket->load(['tenant:id,name', 'opener:id,name']);

        return Inertia::render('rootadmin/support/show', [
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'tenant' => $ticket->tenant?->name,
                'tenant_id' => $ticket->tenant_id,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'priority' => $ticket->priority->value,
                'priority_label' => $ticket->priority->label(),
                'opened_by' => $ticket->opener?->name,
                'opened_at' => PlatformTime::dateTime($ticket->created_at),
                'last_activity_at' => PlatformTime::dateTime($ticket->last_activity_at),
                'closed_at' => PlatformTime::dateTime($ticket->closed_at),
            ],
            'events' => $ticket->events()->with('user:id,name')->latest('id')->limit(200)->get()->map(fn (SupportTicketEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'from' => $this->valueLabel($event->type, $event->from_value),
                'to' => $this->valueLabel($event->type, $event->to_value),
                'note' => $event->note,
                'user' => $event->user?->name,
                'at' => PlatformTime::dateTime($event->created_at),
            ])->all(),
            'statuses' => array_map(fn (SupportTicketStatus $item): array => ['value' => $item->value, 'label' => $item->label()], SupportTicketStatus::cases()),
            'priorities' => array_map(fn (SupportTicketPriority $item): array => ['value' => $item->value, 'label' => $item->label()], SupportTicketPriority::cases()),
        ]);
    }

    public function store(StoreSupportTicketRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $actor = $this->actor($request);

        $ticket = DB::transaction(function () use ($data, $actor, $auditLogger): SupportTicket {
            $ticket = new SupportTicket;
            $ticket->forceFill([
                'tenant_id' => $data['tenant_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => SupportTicketPriority::from($data['priority']),
                'status' => SupportTicketStatus::Open,
                'opened_by' => $actor->getKey(),
                'last_activity_at' => now(),
            ])->save();

            $this->event($ticket, 'opened', null, SupportTicketStatus::Open->value, null, $actor->getKey());
            $auditLogger->record('support.ticket_opened', $ticket->tenant, $ticket, ['priority' => $ticket->priority->value], $actor);

            return $ticket;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Chamado aberto.']);

        return to_route('rootadmin.support.show', $ticket);
    }

    public function update(UpdateSupportTicketRequest $request, SupportTicket $ticket, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();
        $actor = $this->actor($request);

        DB::transaction(function () use ($ticket, $data, $actor, $auditLogger): void {
            $ticket = SupportTicket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $changes = [];

            if (filled($data['note'] ?? null)) {
                $this->event($ticket, 'comment', null, null, $data['note'], $actor->getKey());
                $changes['comment'] = true;
            }

            $status = isset($data['status']) ? SupportTicketStatus::from($data['status']) : null;

            if ($status !== null && $status !== $ticket->status) {
                $this->event($ticket, 'status', $ticket->status->value, $status->value, null, $actor->getKey());
                $changes['status'] = ['de' => $ticket->status->value, 'para' => $status->value];
                $ticket->forceFill(['status' => $status, 'closed_at' => $status->isFinal() ? now() : null]);
            }

            $priority = isset($data['priority']) ? SupportTicketPriority::from($data['priority']) : null;

            if ($priority !== null && $priority !== $ticket->priority) {
                $this->event($ticket, 'priority', $ticket->priority->value, $priority->value, null, $actor->getKey());
                $changes['priority'] = ['de' => $ticket->priority->value, 'para' => $priority->value];
                $ticket->forceFill(['priority' => $priority]);
            }

            if ($changes === []) {
                return;
            }

            $ticket->forceFill(['last_activity_at' => now()])->save();
            $auditLogger->record('support.ticket_updated', $ticket->tenant, $ticket, $changes, $actor);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Acompanhamento registrado.']);

        return to_route('rootadmin.support.show', $ticket);
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
            'assistant' => AssistantUsage::query()->where('usage_date', '>=', now()->startOfMonth()->toDateString())->where('provider_failures', '>', 0)
                ->whereIn('tenant_id', Tenant::query()->where('is_homolog', false)->select('id'))
                ->groupBy('tenant_id')->selectRaw('tenant_id, SUM(provider_failures) AS failures')->orderByDesc('failures')->limit(10)->get()
                ->map(fn (AssistantUsage $row): array => ['tenant_id' => $row->tenant_id, 'tenant' => (string) Tenant::query()->whereKey($row->tenant_id)->value('name'), 'detail' => $row->getAttribute('failures').' falha(s) do provedor de IA no mês'])->values()->all(),
        ];
    }

    private function event(SupportTicket $ticket, string $type, ?string $from, ?string $to, ?string $note, ?int $userId): void
    {
        $event = new SupportTicketEvent;
        $event->forceFill([
            'support_ticket_id' => $ticket->getKey(),
            'user_id' => $userId,
            'type' => $type,
            'from_value' => $from,
            'to_value' => $to,
            'note' => $note,
        ])->save();
    }

    private function valueLabel(string $type, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'priority' => SupportTicketPriority::tryFrom($value)?->label(),
            default => SupportTicketStatus::tryFrom($value)?->label(),
        } ?? $value;
    }
}
