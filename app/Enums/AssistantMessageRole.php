<?php

namespace App\Enums;

enum AssistantMessageRole: string
{
    case Visitor = 'visitor';
    case Assistant = 'assistant';
    case Operator = 'operator';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Visitor => 'Cliente',
            self::Assistant => 'Recepcionista virtual',
            self::Operator => 'Atendente',
            self::System => 'Sistema',
        };
    }
}
