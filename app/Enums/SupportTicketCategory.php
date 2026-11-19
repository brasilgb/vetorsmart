<?php

namespace App\Enums;

enum SupportTicketCategory: string
{
    case Technical = 'technical';
    case Access = 'access';
    case Website = 'website';
    case Subscription = 'subscription';
    case Question = 'question';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Problema técnico',
            self::Access => 'Acesso e usuários',
            self::Website => 'Site e domínio',
            self::Subscription => 'Assinatura',
            self::Question => 'Dúvida de uso',
            self::Other => 'Outro assunto',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $item): array => ['value' => $item->value, 'label' => $item->label()], self::cases());
    }
}
