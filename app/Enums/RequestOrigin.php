<?php

namespace App\Enums;

/**
 * Where a request came from: the panel, the request form of the public site, the same form reached from the Regional
 * Portal, the visitor assistant of the site, or the API (reserved for an integration that does not exist yet).
 */
enum RequestOrigin: string
{
    case Panel = 'panel';
    case PublicSite = 'public_site';
    case Ai = 'ai';
    case Api = 'api';
    case Portal = 'portal';

    /**
     * Entry channel: how the request reached the company, apart from the commercial origin. The stored value "portal"
     * is the site's request form opened from the Regional Portal, so its channel is the site form; the portal itself
     * is its commercial origin (see ServiceRequest::commercialOrigin). Derived on read: stored values never change.
     *
     * @return array{key: string, label: string}
     */
    public function channel(): array
    {
        return match ($this) {
            self::PublicSite, self::Portal => ['key' => 'site_form', 'label' => 'Site — formulário'],
            self::Ai => ['key' => 'site_assistant', 'label' => 'Site — atendimento virtual'],
            self::Panel => ['key' => 'panel', 'label' => 'Painel (registro manual)'],
            self::Api => ['key' => 'api', 'label' => 'API'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Panel => 'Painel',
            self::PublicSite => 'Site público',
            self::Ai => 'Site / IA',
            self::Api => 'API',
            self::Portal => 'Portal Regional',
        };
    }
}
