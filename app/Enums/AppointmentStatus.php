<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case NotPerformed = 'not_performed';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return $this === self::Scheduled;
    }

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::Completed => 'Concluído',
            self::NotPerformed => 'Não realizado',
            self::Cancelled => 'Cancelado',
        };
    }
}
