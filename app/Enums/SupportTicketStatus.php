<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::InProgress => 'Em andamento',
            self::Waiting => 'Aguardando a empresa',
            self::Resolved => 'Resolvido',
            self::Closed => 'Encerrado',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Resolved || $this === self::Closed;
    }
}
