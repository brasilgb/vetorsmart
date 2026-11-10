<?php

namespace App\Services\Websites;

use App\Enums\DomainKind;
use App\Enums\DomainStatus;
use App\Models\TenantDomain;
use Illuminate\Support\Facades\Cache;

/**
 * Host patterns Laravel accepts. Anything else is rejected before routing, so an
 * unknown Host header never reaches the application.
 */
class TrustedHosts
{
    public const string CUSTOM_CACHE_KEY = 'platform.verified_custom_hosts';

    /**
     * @return list<string> regular expressions
     */
    public function patterns(): array
    {
        $central = (string) config('platform.central_host');
        $base = (string) config('platform.tenant_base_domain');

        $patterns = [
            '^'.preg_quote($central, '/').'$',
            '^(?:[a-z0-9-]+\.)*'.preg_quote($base, '/').'$',
        ];

        foreach ($this->verifiedCustomHosts() as $host) {
            $patterns[] = '^'.preg_quote($host, '/').'$';
        }

        return $patterns;
    }

    /**
     * @return list<string>
     */
    public function verifiedCustomHosts(): array
    {
        /** @var list<string> $hosts */
        $hosts = Cache::remember(self::CUSTOM_CACHE_KEY, 300, function (): array {
            $names = TenantDomain::query()
                ->where('status', DomainStatus::Verified)
                ->where('kind', DomainKind::Custom)
                ->pluck('hostname');

            return array_values($names->map(fn ($name): string => (string) $name)->all());
        });

        return $hosts;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CUSTOM_CACHE_KEY);
    }
}
