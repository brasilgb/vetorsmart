<?php

namespace App\Enums;

/**
 * Financial situation of a company's subscription. It never changes tenants.status, which is the administrative
 * suspension decided by the platform.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Em teste',
            self::Active => 'Ativa',
            self::PastDue => 'Pagamento pendente',
            self::Suspended => 'Suspensa por falta de pagamento',
            self::Cancelled => 'Cancelada',
        };
    }

    /**
     * Whether the company keeps using the panel. A pending payment still has access during the grace period.
     */
    public function allowsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }

    /**
     * Statuses each status may move to. Anything else is refused by SubscriptionService.
     *
     * @return list<self>
     */
    public function allowedTargets(): array
    {
        return match ($this) {
            self::Trialing => [self::Active, self::PastDue, self::Cancelled],
            self::Active => [self::PastDue, self::Cancelled],
            self::PastDue => [self::Active, self::Suspended, self::Cancelled],
            self::Suspended => [self::Active, self::Cancelled],
            self::Cancelled => [self::Trialing, self::Active],
        };
    }
}
