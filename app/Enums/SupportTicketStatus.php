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

    /**
     * Who has to act next: the platform team, the company, or nobody (finished).
     */
    public function nextActionBy(): ?string
    {
        return match ($this) {
            self::Open, self::InProgress => 'team',
            self::Waiting => 'company',
            self::Resolved, self::Closed => null,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Resolved || $this === self::Closed;
    }
}
