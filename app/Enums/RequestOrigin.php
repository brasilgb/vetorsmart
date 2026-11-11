<?php

namespace App\Enums;

/**
 * Where a request came from. Only the panel creates requests today; the other origins are
 * reserved for integrations that do not exist yet.
 */
enum RequestOrigin: string
{
    case Panel = 'panel';
    case PublicSite = 'public_site';
    case Ai = 'ai';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Panel => 'Painel',
            self::PublicSite => 'Site público',
            self::Ai => 'IA',
            self::Api => 'API',
        };
    }
}
