<?php

namespace App\Services\Assistant;

use App\Enums\AssistantMessageRole;
use App\Enums\AssistantState;
use App\Enums\ConversationStatus;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantOperation;
use App\Models\AssistantSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Conversation persistence and the human side of a conversation: handoff, assumption, reply,
 * return to the assistant and closing. The visitor is identified by a random token that is only
 * sent to the browser; the database keeps its SHA-256 hash.
 */
class AssistantConversations
{
    private const int MAX_BODY_CHARS = 2000;

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Start a conversation, or resume the one matching the visitor's token. Returns the new token
     * only when a conversation was created, so the caller sets the cookie exactly once.
     *
     * @return array{conversation: AssistantConversation, token: ?string, created: bool}
     */
    public function start(Tenant $tenant, AssistantSetting $setting, ?string $token): array
    {
        if ($token !== null && $token !== '') {
            $existing = AssistantConversation::query()
                ->forTenant($tenant)
                ->where('session_hash', $this->hash($token))
                ->where('status', '!=', ConversationStatus::Closed->value)
                ->first();

            if ($existing !== null) {
                return ['conversation' => $existing, 'token' => null, 'created' => false];
            }
        }

        $newToken = Str::random(48);

        $conversation = AssistantConversation::create([
            'tenant_id' => $tenant->getKey(),
            'channel' => 'site',
            'session_hash' => $this->hash($newToken),
            'state' => AssistantState::Greeting,
            'status' => ConversationStatus::Active,
            'collected' => [],
            'last_activity_at' => now(),
            'retention_until' => now()->addDays($setting->retention_days ?? (int) config('assistant.default_retention_days')),
        ]);

        return ['conversation' => $conversation, 'token' => $newToken, 'created' => true];
    }

    /**
     * The conversation that belongs to a visitor token in this tenant, or null.
     */
    public function findByToken(Tenant $tenant, ?string $token): ?AssistantConversation
    {
        if ($token === null || $token === '') {
            return null;
        }

        return AssistantConversation::query()
            ->forTenant($tenant)
            ->where('session_hash', $this->hash($token))
            ->first();
    }

    /**
     * Process one visitor message at most once per client id. A resend of the same message (the first response was lost)
     * finds the id already recorded and does nothing. The conversation row is locked, so simultaneous sends are ordered.
     *
     * @param  \Closure(AssistantConversation): void  $process
     */
    public function receiveOnce(AssistantConversation $conversation, ?string $clientId, \Closure $process): void
    {
        DB::transaction(function () use ($conversation, $clientId, $process): void {
            $locked = AssistantConversation::query()->whereKey($conversation->getKey())->lockForUpdate()->firstOrFail();

            if ($clientId !== null) {
                $key = hash('sha256', implode('|', [$locked->getKey(), 'visitor_message', $clientId]));

                if (AssistantOperation::query()->where('tenant_id', $locked->tenant_id)->where('idempotency_key', $key)->exists()) {
                    return;
                }

                AssistantOperation::create([
                    'tenant_id' => $locked->tenant_id,
                    'assistant_conversation_id' => $locked->getKey(),
                    'operation' => 'visitor_message',
                    'idempotency_key' => $key,
                ]);
            }

            $process($locked);
        });
    }

    public function appendMessage(AssistantConversation $conversation, AssistantMessageRole $role, string $body, ?User $user = null): AssistantMessage
    {
        return AssistantMessage::create([
            'tenant_id' => $conversation->tenant_id,
            'assistant_conversation_id' => $conversation->getKey(),
            'role' => $role->value,
            'body' => Str::limit(trim($body), self::MAX_BODY_CHARS, ''),
            'user_id' => $user?->getKey(),
        ]);
    }

    /**
     * Messages after the given id, in order. Used by the visitor page and by the operator inbox.
     *
     * @return list<array{id: int, role: string, role_label: string, body: string, at: string}>
     */
    public function messagesAfter(AssistantConversation $conversation, int $afterId = 0): array
    {
        return $conversation->messages()
            ->where('id', '>', $afterId)
            ->get()
            ->map(fn (AssistantMessage $message): array => [
                'id' => $message->getKey(),
                'role' => $message->role,
                'role_label' => AssistantMessageRole::from($message->role)->label(),
                'body' => $message->body,
                'at' => $message->created_at?->format('H:i') ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * Stop the automatic assistant and register the reason, with a summary for the human who continues.
     */
    public function handoff(AssistantConversation $conversation, string $reason): void
    {
        $conversation->forceFill([
            'status' => ConversationStatus::AwaitingHuman,
            'state' => AssistantState::Human,
            'handoff_reason' => Str::limit($reason, 250, ''),
            'collected' => [...($conversation->collected ?? []), 'summary' => $this->summarize($conversation, $reason)],
        ])->save();
    }

    /**
     * A reader can assume a conversation waiting for a human. Only one human holds it at a time.
     *
     * @throws ValidationException
     */
    public function assume(AssistantConversation $conversation, User $user): AssistantConversation
    {
        return DB::transaction(function () use ($conversation, $user): AssistantConversation {
            $conversation = AssistantConversation::query()->whereKey($conversation->getKey())->lockForUpdate()->firstOrFail();

            if ($conversation->status === ConversationStatus::Closed) {
                throw ValidationException::withMessages(['status' => ['Esta conversa já foi encerrada.']]);
            }

            if ($conversation->status === ConversationStatus::HumanAssumed && $conversation->handled_by_user_id !== $user->getKey()) {
                throw ValidationException::withMessages(['status' => ['Esta conversa já está com outro atendente.']]);
            }

            $conversation->forceFill([
                'status' => ConversationStatus::HumanAssumed,
                'handled_by_user_id' => $user->getKey(),
            ])->save();

            $this->appendMessage($conversation, AssistantMessageRole::System, 'Um atendente assumiu a conversa.');
            $this->auditLogger->record('assistant.conversation_assumed', $conversation->tenant, $conversation, [], $user);

            return $conversation;
        });
    }

    /**
     * Operator reply. Only the attendant who assumed the conversation may write in it.
     *
     * @throws ValidationException
     */
    public function operatorReply(AssistantConversation $conversation, User $user, string $body): AssistantMessage
    {
        if ($conversation->status !== ConversationStatus::HumanAssumed || $conversation->handled_by_user_id !== $user->getKey()) {
            throw ValidationException::withMessages(['body' => ['Assuma a conversa antes de responder.']]);
        }

        return $this->appendMessage($conversation, AssistantMessageRole::Operator, $body, $user);
    }

    /**
     * Give the conversation back to the automatic assistant, resuming at the step the data allows.
     */
    public function returnToAssistant(AssistantConversation $conversation, User $user): AssistantConversation
    {
        $conversation->forceFill([
            'status' => ConversationStatus::Active,
            'state' => $this->resumeState($conversation),
            'handled_by_user_id' => null,
            'handoff_reason' => null,
        ])->save();

        $this->appendMessage($conversation, AssistantMessageRole::System, 'A recepcionista virtual retomou o atendimento.');
        $this->auditLogger->record('assistant.conversation_returned', $conversation->tenant, $conversation, [], $user);

        return $conversation;
    }

    public function close(AssistantConversation $conversation, User $user): AssistantConversation
    {
        $conversation->forceFill(['status' => ConversationStatus::Closed, 'handled_by_user_id' => $conversation->handled_by_user_id ?? $user->getKey()])->save();

        $this->appendMessage($conversation, AssistantMessageRole::System, 'Atendimento encerrado. Obrigado pelo contato.');
        $this->auditLogger->record('assistant.conversation_closed', $conversation->tenant, $conversation, [], $user);

        return $conversation;
    }

    /**
     * The first step that still lacks data. Uses only what is stored, so nothing is asked twice.
     */
    public function resumeState(AssistantConversation $conversation): AssistantState
    {
        return match (true) {
            $conversation->appointment_id !== null => AssistantState::Done,
            $conversation->service_id !== null && $conversation->customer_id !== null => AssistantState::Date,
            $conversation->service_id !== null => AssistantState::Customer,
            default => AssistantState::Service,
        };
    }

    /**
     * Summary of the conversation for the team: reason, service, customer, preferred time, appointment and the last messages.
     */
    public function summarize(AssistantConversation $conversation, ?string $reason = null): string
    {
        $lines = [];

        if ($reason !== null) {
            $lines[] = 'Motivo: '.$reason;
        }

        $lines[] = 'Serviço: '.($conversation->service?->name ?? 'não informado').'.';
        $lines[] = 'Cliente: '.($conversation->customer?->name ?? 'não identificado').'.';

        if (filled($conversation->collected['preferred_time'] ?? null)) {
            $lines[] = 'Horário preferido pelo cliente: '.$conversation->collected['preferred_time'].'.';
        }

        if ($conversation->appointment_id !== null) {
            $lines[] = 'Agendamento registrado na agenda.';
        }

        $dialogue = $conversation->messages()
            ->whereIn('role', [AssistantMessageRole::Visitor->value, AssistantMessageRole::Assistant->value, AssistantMessageRole::Operator->value])
            ->latest('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->map(fn (AssistantMessage $message): string => AssistantMessageRole::from($message->role)->label().': '.$message->body)
            ->implode("\n");

        return implode(' ', $lines)."\nConversa:\n".$dialogue;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
