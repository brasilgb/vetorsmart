<?php

namespace App\Enums;

/**
 * Steps of the visitor flow. The engine only accepts the operations allowed in the current step.
 */
enum AssistantState: string
{
    case Greeting = 'greeting';
    case Need = 'need';
    case Service = 'service';
    case Customer = 'customer';
    case Date = 'date';
    case Slots = 'slots';
    case Confirm = 'confirm';
    case Done = 'done';
    case Human = 'human';

    public function label(): string
    {
        return match ($this) {
            self::Greeting => 'Saudação',
            self::Need => 'Identificação da necessidade',
            self::Service => 'Serviço',
            self::Customer => 'Identificação do cliente',
            self::Date => 'Data',
            self::Slots => 'Horário',
            self::Confirm => 'Confirmação',
            self::Done => 'Concluído',
            self::Human => 'Atendimento humano',
        };
    }
}
