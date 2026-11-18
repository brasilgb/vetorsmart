<?php

namespace App\Enums;

/**
 * Payment methods offered for the subscription. Each period gets its own charge, so the renewal does not depend on
 * the recurrence features of each method, which differ at Efí (see docs/VETORSMART-BILLING-01.md). Card is not
 * offered yet: it needs the browser tokenization script of Efí.
 */
enum PaymentMethod: string
{
    case Pix = 'pix';
    case Billet = 'boleto';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::Billet => 'Boleto (com Pix)',
        };
    }
}
