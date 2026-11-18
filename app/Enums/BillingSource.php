<?php

namespace App\Enums;

/**
 * Who caused a change in a subscription.
 */
enum BillingSource: string
{
    case Company = 'company';
    case RootAdmin = 'rootadmin';
    case Gateway = 'gateway';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Empresa',
            self::RootAdmin => 'Administração',
            self::Gateway => 'Efí',
            self::System => 'Rotina diária',
        };
    }
}
