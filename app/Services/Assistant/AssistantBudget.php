<?php

namespace App\Services\Assistant;

use App\Models\AssistantSetting;
use App\Models\AssistantUsage;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * Daily consumption per tenant. Counters are written in the same request that consumes them, so a
 * limit cannot be exceeded by a burst of concurrent messages without being recorded.
 */
class AssistantBudget
{
    public function messagesAllowed(Tenant $tenant): bool
    {
        $limit = $this->setting($tenant)->daily_message_limit;

        return $this->today($tenant)->messages < $limit;
    }

    public function providerAllowed(Tenant $tenant): bool
    {
        return $this->today($tenant)->tokens < $this->setting($tenant)->daily_token_limit;
    }

    public function recordMessage(Tenant $tenant): void
    {
        $this->ensureRow($tenant)->increment('messages');
    }

    public function recordProviderFailure(Tenant $tenant): void
    {
        $this->ensureRow($tenant)->increment('provider_failures');
    }

    public function recordProviderCall(Tenant $tenant, int $tokens): void
    {
        $row = $this->ensureRow($tenant);
        $row->increment('provider_calls');
        $row->increment('tokens', max(0, $tokens));
    }

    /**
     * Settings for the tenant; a tenant without settings gets the defaults, with the assistant disabled.
     */
    public function setting(Tenant $tenant): AssistantSetting
    {
        return AssistantSetting::query()->forTenant($tenant)->first() ?? new AssistantSetting([
            'tenant_id' => $tenant->getKey(),
            'enabled' => false,
            'daily_message_limit' => 200,
            'daily_token_limit' => 100000,
            'retention_days' => (int) config('assistant.default_retention_days'),
        ]);
    }

    private function today(Tenant $tenant): AssistantUsage
    {
        return AssistantUsage::query()
            ->forTenant($tenant)
            ->where('usage_date', $this->date($tenant))
            ->first() ?? new AssistantUsage(['messages' => 0, 'provider_calls' => 0, 'tokens' => 0]);
    }

    private function ensureRow(Tenant $tenant): AssistantUsage
    {
        return AssistantUsage::query()->firstOrCreate(
            ['tenant_id' => $tenant->getKey(), 'usage_date' => $this->date($tenant)],
            ['messages' => 0, 'provider_calls' => 0, 'tokens' => 0],
        );
    }

    private function date(Tenant $tenant): string
    {
        return CarbonImmutable::now($tenant->timezone)->toDateString();
    }
}
