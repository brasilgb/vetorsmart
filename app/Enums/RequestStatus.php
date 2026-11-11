<?php

namespace App\Enums;

/**
 * Workflow of a service request: Solicitação → Triagem → Agendamento → Execução → Conclusão.
 * Completed, not performed and cancelled are closed states.
 */
enum RequestStatus: string
{
    case Requested = 'requested';
    case Triaged = 'triaged';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case NotPerformed = 'not_performed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Triaged, self::Cancelled],
            self::Triaged => [self::Scheduled, self::Cancelled],
            self::Scheduled => [self::InProgress, self::NotPerformed, self::Cancelled, self::Triaged],
            self::InProgress => [self::Completed, self::NotPerformed],
            self::Completed, self::NotPerformed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::NotPerformed, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitado',
            self::Triaged => 'Em triagem',
            self::Scheduled => 'Agendado',
            self::InProgress => 'Em andamento',
            self::Completed => 'Concluído',
            self::NotPerformed => 'Não realizado',
            self::Cancelled => 'Cancelado',
        };
    }
}
