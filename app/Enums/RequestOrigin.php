<?php

namespace App\Enums;

/**
 * Where a request came from: the panel, the request form of the public site, the visitor assistant of the site, or
 * the API (reserved for an integration that does not exist yet).
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
            self::Ai => 'Site / IA',
            self::Api => 'API',
        };
    }
}
