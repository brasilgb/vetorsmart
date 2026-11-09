<?php

namespace App\Enums;

enum PriceType: string
{
    case Fixed = 'fixed';
    case StartingAt = 'starting_at';
    case OnRequest = 'on_request';

    /**
     * Whether this price type carries a monetary amount.
     */
    public function requiresAmount(): bool
    {
        return $this !== self::OnRequest;
    }
}
