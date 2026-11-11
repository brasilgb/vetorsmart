<?php

namespace App\Console\Commands;

use App\Models\AssistantConversation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Removes visitor conversations past their retention date. Messages and operation records are
 * removed with them; service requests and appointments are business records and stay.
 */
#[Signature('assistant:purge {--chunk=500 : Conversations removed per batch}')]
#[Description('Remove visitor conversations whose retention date has passed')]
class PurgeAssistantConversations extends Command
{
    public function handle(): int
    {
        $removed = 0;

        do {
            $ids = AssistantConversation::query()
                ->where('retention_until', '<', now())
                ->orderBy('id')
                ->limit((int) $this->option('chunk'))
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $removed += AssistantConversation::query()->whereIn('id', $ids)->delete();
            }
        } while ($ids->isNotEmpty());

        $this->info("Conversas expiradas removidas: {$removed}.");

        return self::SUCCESS;
    }
}
