<?php

namespace App\Services\Assistant;

/**
 * Replaceable language-model provider. It only classifies a visitor message; it never executes
 * operations, never chooses tenants and never writes records. Its answers are validated before use.
 */
interface AssistantProvider
{
    /**
     * Identifier stored in the usage records, for example "none" or "openai".
     */
    public function name(): string;

    /**
     * Classify a visitor message given a compact context.
     *
     * @param  array{services: list<array{index: int, name: string}>, state: string, max_tokens: int}  $context
     * @return array{intent: string, service_index?: int}|null null when the provider cannot classify
     *
     * @throws \RuntimeException when the provider is unavailable
     */
    public function classify(string $message, array $context): ?array;
}
