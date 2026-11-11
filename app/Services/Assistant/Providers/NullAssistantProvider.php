<?php

namespace App\Services\Assistant\Providers;

use App\Services\Assistant\AssistantProvider;

/**
 * Default provider: no language model is called, so the deterministic flow handles every message.
 */
class NullAssistantProvider implements AssistantProvider
{
    public function name(): string
    {
        return 'none';
    }

    public function classify(string $message, array $context): ?array
    {
        return null;
    }
}
