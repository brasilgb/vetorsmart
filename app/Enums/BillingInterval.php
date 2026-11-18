<?php

namespace App\Enums;

/**
 * Billing cycles of the plan. Each cycle has its own total price; the period of a charge is the number of months of
 * the cycle chosen when the charge is created.
 */
enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Semiannual => 6,
            self::Annual => 12,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensal',
            self::Semiannual => 'Semestral',
            self::Annual => 'Anual',
        };
    }
}
