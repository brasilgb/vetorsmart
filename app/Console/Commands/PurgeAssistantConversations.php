<?php

namespace App\Console\Commands;

use App\Enums\ConversationStatus;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantSetting;
use App\Models\Tenant;
use DateTimeInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Retention of visitor conversations, per company.
 *
 * Each company sets for how many days its conversations are kept (assistant settings, 1 to 365, 30 by default); a
 * conversation records its own retention date when it starts and stops being reachable by the visitor on that date.
 *
 * Without --execute this is a simulation: it reports, per company, what has passed its retention date and removes
 * nothing. The scheduler runs it in this mode every day. With --execute it removes the expired conversations with
 * their messages and operation records; service requests and appointments are business records and always stay.
 * Removal additionally needs ASSISTANT_RETENTION_DELETION=true, so it cannot start before the retention policy is
 * decided.
 */
#[Signature('assistant:purge
    {--execute : Remove the expired conversations instead of only reporting them}
    {--tenant= : Limit to one company, by id}
    {--chunk=500 : Conversations removed per batch}')]
#[Description('Report (default) or remove visitor conversations whose retention date has passed')]
class PurgeAssistantConversations extends Command
{
    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $tenantId = $this->option('tenant');

        if ($tenantId !== null && (! ctype_digit((string) $tenantId) || ! Tenant::query()->whereKey((int) $tenantId)->exists())) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        if ($execute && ! config('assistant.retention_deletion_enabled')) {
            $this->error('Exclusão desativada. Defina a política de retenção e ASSISTANT_RETENTION_DELETION=true antes de usar --execute.');

            return self::FAILURE;
        }

        $now = now();
        $report = $this->report($tenantId === null ? null : (int) $tenantId, $now);

        $conversations = array_sum(array_column($report, 'conversations'));
        $messages = array_sum(array_column($report, 'messages'));

        if ($report !== []) {
            $this->table(
                ['Empresa', 'Dias de retenção', 'Conversas vencidas', 'Ainda em aberto', 'Mensagens', 'Vencida há mais tempo'],
                array_map(fn (array $row): array => [
                    '#'.$row['tenant_id'].' '.$row['tenant'],
                    $row['retention_days'],
                    $row['conversations'],
                    $row['open'],
                    $row['messages'],
                    $row['oldest'],
                ], $report),
            );
        }

        if (! $execute) {
            $this->line(sprintf(
                '[assistant:purge] simulação em %s: %d conversa(s) vencida(s) em %d empresa(s), com %d mensagem(ns). Nada foi removido.',
                $now->toDateTimeString(),
                $conversations,
                count($report),
                $messages,
            ));

            return self::SUCCESS;
        }

        $removed = 0;

        do {
            $ids = $this->expired($tenantId === null ? null : (int) $tenantId, $now)
                ->orderBy('id')
                ->limit(max(1, (int) $this->option('chunk')))
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $removed += AssistantConversation::query()->whereIn('id', $ids)->delete();
            }
        } while ($ids->isNotEmpty());

        $this->line(sprintf('[assistant:purge] exclusão em %s: %d conversa(s) vencida(s) removida(s).', $now->toDateTimeString(), $removed));

        return self::SUCCESS;
    }

    /**
     * Expired conversations grouped by company, with grouped queries only: no conversation content is read.
     *
     * @return list<array{tenant_id: int, tenant: string, retention_days: int, conversations: int, open: int, messages: int, oldest: string}>
     */
    private function report(?int $tenantId, DateTimeInterface $now): array
    {
        $rows = $this->expired($tenantId, $now)
            ->selectRaw('tenant_id, COUNT(*) as conversations, SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END) as open, MIN(retention_until) as oldest', [ConversationStatus::Closed->value])
            ->groupBy('tenant_id')
            ->orderBy('tenant_id')
            ->toBase()
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $tenantIds = $rows->pluck('tenant_id')->all();

        $messages = AssistantMessage::query()
            ->whereIn('assistant_conversation_id', $this->expired($tenantId, $now)->select('id'))
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $tenants = Tenant::query()->whereKey($tenantIds)->pluck('name', 'id');

        $days = AssistantSetting::query()->whereIn('tenant_id', $tenantIds)->pluck('retention_days', 'tenant_id');

        return array_values($rows->map(fn ($row): array => [
            'tenant_id' => (int) $row->tenant_id,
            'tenant' => (string) ($tenants[$row->tenant_id] ?? '—'),
            'retention_days' => (int) ($days[$row->tenant_id] ?? config('assistant.default_retention_days')),
            'conversations' => (int) $row->conversations,
            'open' => (int) $row->open,
            'messages' => (int) ($messages[$row->tenant_id] ?? 0),
            'oldest' => (string) $row->oldest,
        ])->all());
    }

    /**
     * @return Builder<AssistantConversation>
     */
    private function expired(?int $tenantId, DateTimeInterface $now): Builder
    {
        return AssistantConversation::query()
            ->where('retention_until', '<', $now)
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId));
    }
}
