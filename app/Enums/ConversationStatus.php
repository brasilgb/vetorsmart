<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case Active = 'active';
    case AwaitingHuman = 'awaiting_human';
    case HumanAssumed = 'human_assumed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Com a IA',
            self::AwaitingHuman => 'Aguardando atendente',
            self::HumanAssumed => 'Com atendente',
            self::Closed => 'Encerrada',
        };
    }

    /** The automatic assistant may answer only while the conversation is with it. */
    public function allowsAutomaticReplies(): bool
    {
        return $this === self::Active;
    }
}
