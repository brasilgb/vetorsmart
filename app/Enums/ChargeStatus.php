<?php

namespace App\Enums;

enum ChargeStatus: string
{
    /** Created locally; the gateway has not issued it yet (or the last attempt failed and will be retried). */
    case Pending = 'pending';
    /** Issued at the gateway and waiting for payment. */
    case Waiting = 'waiting';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Em emissão',
            self::Waiting => 'Aguardando pagamento',
            self::Paid => 'Paga',
            self::Cancelled => 'Cancelada',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Waiting;
    }
}
