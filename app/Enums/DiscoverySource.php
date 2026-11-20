<?php

namespace App\Enums;

/**
 * How the customer found the company, apart from the channel the request came through (RequestOrigin). Recorded only
 * when the platform can vouch for it: today, a visit that arrived through a signed link of the Regional Portal. Null
 * means unknown (direct visit, old record, or panel).
 */
enum DiscoverySource: string
{
    case Portal = 'portal';

    public function label(): string
    {
        return match ($this) {
            self::Portal => 'Portal Regional',
        };
    }
}
