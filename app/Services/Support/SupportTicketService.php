<?php

namespace App\Services\Support;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change of a support request. Each runs in a transaction with a row lock, writes the append-only history
 * (support_ticket_events) and the audit log.
 *
 * - Status changes follow ALLOWED transitions; a closed request only comes back through reopen().
 * - A company reply on a request waiting for it, or already resolved, puts it back to "Aberto"; a closed request does
 *   not accept company replies (the company opens a new one).
 * - Internal notes of the platform team are flagged and never shown to the company.
 */
class SupportTicketService
{
    /** @var array<string, list<string>> */
    private const array ALLOWED = [
        'open' => ['in_progress', 'waiting', 'resolved', 'closed'],
        'in_progress' => ['open', 'waiting', 'resolved', 'closed'],
        'waiting' => ['open', 'in_progress', 'resolved', 'closed'],
        'resolved' => ['closed'],
        'closed' => [],
    ];

    public function __construct(private AuditLogger $auditLogger) {}

    public function open(?Tenant $tenant, string $subject, string $description, SupportTicketCategory $category, SupportTicketPriority $priority, string $via, User $actor): SupportTicket
    {
        return DB::transaction(function () use ($tenant, $subject, $description, $category, $priority, $via, $actor): SupportTicket {
            $ticket = new SupportTicket;
            $ticket->forceFill([
                'tenant_id' => $tenant?->getKey(),
                'subject' => $subject,
                'description' => $description,
                'category' => $category,
                'priority' => $priority,
                'status' => SupportTicketStatus::Open,
                'opened_via' => $via,
                'opened_by' => $actor->getKey(),
                'last_activity_at' => now(),
            ])->save();

            $this->event($ticket, 'opened', null, SupportTicketStatus::Open->value, null, false, $actor);
            $this->auditLogger->record('support.ticket_opened', $tenant, $ticket, ['priority' => $priority->value, 'category' => $category->value, 'via' => $via], $actor);

            return $ticket;
        });
    }

    /**
     * A reply from the company that owns the request.
     */
    public function companyReply(SupportTicket $ticket, string $note, User $actor): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $note, $actor): SupportTicket {
            $ticket = $this->locked($ticket);

            if ($ticket->status === SupportTicketStatus::Closed) {
                throw ValidationException::withMessages(['note' => 'Este chamado foi encerrado. Abra um novo chamado para continuar.']);
            }

            $this->event($ticket, 'comment', null, null, $note, false, $actor);
            $changes = ['reply' => true];

            if (in_array($ticket->status, [SupportTicketStatus::Waiting, SupportTicketStatus::Resolved], true)) {
                $type = $ticket->status === SupportTicketStatus::Resolved ? 'reopened' : 'status';
                $this->event($ticket, $type, $ticket->status->value, SupportTicketStatus::Open->value, null, false, $actor);
                $changes['status'] = ['de' => $ticket->status->value, 'para' => SupportTicketStatus::Open->value];
                $ticket->forceFill(['status' => SupportTicketStatus::Open, 'closed_at' => null]);
            }

            $ticket->forceFill(['last_activity_at' => now()])->save();
            $this->auditLogger->record('support.ticket_replied', $ticket->tenant, $ticket, $changes, $actor);

            return $ticket;
        });
    }

    /**
     * Follow-up by the platform team: a reply (seen by the company) or an internal note, a controlled status change,
     * priority, person responsible and due date. Only the given fields change.
     *
     * @param  array{note?: string|null, internal?: bool, status?: SupportTicketStatus|null, priority?: SupportTicketPriority|null, assigned_to?: User|null, assign?: bool, due_at?: CarbonInterface|null, set_due?: bool}  $data
     */
    public function platformUpdate(SupportTicket $ticket, array $data, User $actor): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $data, $actor): SupportTicket {
            $ticket = $this->locked($ticket);
            $changes = [];

            if (filled($data['note'] ?? null)) {
                $internal = (bool) ($data['internal'] ?? false);
                $this->event($ticket, $internal ? 'internal' : 'comment', null, null, (string) $data['note'], $internal, $actor);
                $changes[$internal ? 'internal_note' : 'reply'] = true;
            }

            $status = $data['status'] ?? null;

            if ($status !== null && $status !== $ticket->status) {
                if (! in_array($status->value, self::ALLOWED[$ticket->status->value], true)) {
                    throw ValidationException::withMessages(['status' => "Mudança não permitida: {$ticket->status->label()} → {$status->label()}. Use Reabrir para um chamado finalizado."]);
                }

                $this->event($ticket, 'status', $ticket->status->value, $status->value, null, false, $actor);
                $changes['status'] = ['de' => $ticket->status->value, 'para' => $status->value];
                $ticket->forceFill(['status' => $status, 'closed_at' => $status->isFinal() ? now() : null]);
            }

            $priority = $data['priority'] ?? null;

            if ($priority !== null && $priority !== $ticket->priority) {
                $this->event($ticket, 'priority', $ticket->priority->value, $priority->value, null, true, $actor);
                $changes['priority'] = ['de' => $ticket->priority->value, 'para' => $priority->value];
                $ticket->forceFill(['priority' => $priority]);
            }

            if (($data['assign'] ?? false) === true) {
                $assignee = $data['assigned_to'] ?? null;

                if ($assignee !== null && ! $assignee->isRootAdmin()) {
                    throw ValidationException::withMessages(['assigned_to' => 'O responsável precisa ser um administrador da plataforma ativo.']);
                }

                if ($assignee?->getKey() !== $ticket->assigned_to) {
                    $this->event($ticket, 'assigned', $ticket->assigned_to === null ? null : (string) $ticket->assigned_to, $assignee === null ? null : (string) $assignee->getKey(), null, true, $actor);
                    $changes['assigned_to'] = ['de' => $ticket->assigned_to, 'para' => $assignee?->getKey()];
                    $ticket->forceFill(['assigned_to' => $assignee?->getKey()]);
                }
            }

            if (($data['set_due'] ?? false) === true) {
                $due = $data['due_at'] ?? null;

                if ($due?->toDateTimeString() !== $ticket->due_at?->toDateTimeString()) {
                    $this->event($ticket, 'due', $ticket->due_at?->toDateTimeString(), $due?->toDateTimeString(), null, true, $actor);
                    $changes['due_at'] = ['de' => $ticket->due_at?->toDateTimeString(), 'para' => $due?->toDateTimeString()];
                    $ticket->forceFill(['due_at' => $due]);
                }
            }

            if ($changes === []) {
                return $ticket;
            }

            $ticket->forceFill(['last_activity_at' => now()])->save();
            $this->auditLogger->record('support.ticket_updated', $ticket->tenant, $ticket, $changes, $actor);

            return $ticket;
        });
    }

    /**
     * Bring a resolved or closed request back to "Aberto" (platform team).
     */
    public function reopen(SupportTicket $ticket, User $actor, string $reason): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $actor, $reason): SupportTicket {
            $ticket = $this->locked($ticket);

            if (! $ticket->status->isFinal()) {
                throw ValidationException::withMessages(['reason' => 'Só é possível reabrir um chamado resolvido ou encerrado.']);
            }

            $this->event($ticket, 'reopened', $ticket->status->value, SupportTicketStatus::Open->value, $reason, false, $actor);
            $this->auditLogger->record('support.ticket_reopened', $ticket->tenant, $ticket, ['de' => $ticket->status->value, 'reason' => $reason], $actor);
            $ticket->forceFill(['status' => SupportTicketStatus::Open, 'closed_at' => null, 'last_activity_at' => now()])->save();

            return $ticket;
        });
    }

    private function locked(SupportTicket $ticket): SupportTicket
    {
        return SupportTicket::query()->with('tenant')->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
    }

    private function event(SupportTicket $ticket, string $type, ?string $from, ?string $to, ?string $note, bool $internal, User $actor): void
    {
        $event = new SupportTicketEvent;
        $event->forceFill([
            'support_ticket_id' => $ticket->getKey(),
            'user_id' => $actor->getKey(),
            'type' => $type,
            'from_value' => $from,
            'to_value' => $to,
            'note' => $note,
            'is_internal' => $internal,
        ])->save();
    }
}
