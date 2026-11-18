<?php

namespace App\Enums;

enum ConfirmationPolicy: string
{
    case None = 'none';
    case Manual = 'manual';
    case CustomerConfirmation = 'customer_confirmation';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Sem confirmação',
            self::Manual => 'Confirmação pela equipe',
            self::CustomerConfirmation => 'Cliente confirma',
        };
    }
}
