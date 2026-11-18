<?php

namespace App\Enums;

enum AiCreditPurchaseStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Aguardando pagamento',
            self::Paid => 'Paga',
            self::Cancelled => 'Cancelada',
            self::PartiallyRefunded => 'Estornada em parte',
            self::Refunded => 'Estornada',
        };
    }
}
