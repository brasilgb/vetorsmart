<?php

namespace App\Enums;

enum ServiceMode: string
{
    case InPerson = 'in_person';
    case Remote = 'remote';
    case CustomerLocation = 'customer_location';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'Presencial',
            self::Remote => 'Remoto',
            self::CustomerLocation => 'No local do cliente',
        };
    }
}
