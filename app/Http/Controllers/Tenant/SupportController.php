<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSupportRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\Tenant;
use App\Services\Support\SupportTicketService;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Central de Suporte of the company: open requests, follow them and reply. A request is always looked up inside the
 * company of the session (another company's request answers 404), and the internal notes of the platform team are
 * never sent to the screen. Reachable even while the subscription blocks the rest of the panel.
 */
class SupportController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $tenant = $context->requireTenant();
        Gate::authorize('useSupport', $tenant);

        $filter = $request->string('situacao')->value();
        $tickets = SupportTicket::query()->where('tenant_id', $tenant->id)
            ->when($filter === 'abertos', fn ($query) => $query->whereNotIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value]))
            ->when($filter === 'finalizados', fn ($query) => $query->whereIn('status', [SupportTicketStatus::Resolved->value, SupportTicketStatus::Closed->value]))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('tenant/support/index', [
            'filter' => $filter,
            'categories' => SupportTicketCategory::options(),
            'tickets' => $tickets->through(fn (SupportTicket $ticket): array => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'category_label' => $ticket->category->label(),
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'is_final' => $ticket->status->isFinal(),
                'next_action' => $ticket->status->nextActionBy(),
                'opened_at' => $this->local($tenant, $ticket->created_at),
                'last_activity_at' => $this->local($tenant, $ticket->last_activity_at),
            ]),
        ]);
    }

    public function store(StoreSupportRequest $request, TenantContext $context, SupportTicketService $support): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('useSupport', $tenant);

        $data = $request->validated();
        $ticket = $support->open($tenant, $data['subject'], $data['description'], SupportTicketCategory::from($data['category']), SupportTicketPriority::Normal, 'tenant', $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Chamado aberto. A equipe VetorSmart vai responder por aqui.']);

        return to_route('tenant.support.show', ['ticket' => $ticket->id]);
    }

    public function show(TenantContext $context, int $ticket): Response
    {
        $tenant = $context->requireTenant();
        Gate::authorize('useSupport', $tenant);

        $ticket = $this->find($tenant, $ticket);
        $ticket->load('opener:id,name');

        return Inertia::render('tenant/support/show', [
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'category_label' => $ticket->category->label(),
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'is_closed' => $ticket->status === SupportTicketStatus::Closed,
                'next_action' => $ticket->status->nextActionBy(),
                'opened_by' => $ticket->opener?->name,
                'opened_at' => $this->local($tenant, $ticket->created_at),
                'closed_at' => $this->local($tenant, $ticket->closed_at),
            ],
            'events' => $ticket->events()->where('is_internal', false)->with('user:id,name,is_root_admin')->orderBy('id')->get()->map(fn (SupportTicketEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'from' => SupportTicketStatus::tryFrom((string) $event->from_value)?->label(),
                'to' => SupportTicketStatus::tryFrom((string) $event->to_value)?->label(),
                'note' => $event->note,
                'author' => $event->user?->is_root_admin ? 'Equipe VetorSmart' : ($event->user->name ?? '—'),
                'from_platform' => (bool) $event->user?->is_root_admin,
                'at' => $this->local($tenant, $event->created_at),
            ])->all(),
        ]);
    }

    public function reply(Request $request, TenantContext $context, SupportTicketService $support, int $ticket): RedirectResponse
    {
        $tenant = $context->requireTenant();
        Gate::authorize('useSupport', $tenant);

        $data = $request->validate(['note' => ['required', 'string', 'min:2', 'max:5000']], ['note.*' => 'Escreva a resposta (até 5.000 caracteres).']);
        $support->companyReply($this->find($tenant, $ticket), $data['note'], $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Resposta enviada.']);

        return to_route('tenant.support.show', ['ticket' => $ticket]);
    }

    private function find(Tenant $tenant, int $ticket): SupportTicket
    {
        return SupportTicket::query()->where('tenant_id', $tenant->id)->whereKey($ticket)->firstOrFail();
    }

    private function local(Tenant $tenant, ?CarbonInterface $instant): ?string
    {
        return $instant === null ? null : $tenant->toLocal($instant)->format('d/m/Y H:i');
    }
}
