<?php

namespace App\Services\Billing;

use App\Models\BillingSetting;
use App\Models\Plan;

/**
 * Whether billing is in force. Enforcement applies only when BOTH:
 *
 * - the environment switch PLATFORM_BILLING_ENFORCEMENT_ENABLED is the literal `true`; and
 * - the start recorded by billing:start-enforcement (billing_settings.enforcement_starts_at) has been reached.
 *
 * A missing or invalid configuration keeps it off. While off, subscriptions are history only: trials never end in a
 * suspension, no charge is opened, no paid seat or credit is granted and the panel stays open. This is independent of
 * the four billing locks, which still decide whether a real charge can reach Efí once enforcement is on.
 */
class BillingEnforcement
{
    public function enabledByEnvironment(): bool
    {
        return config('platform.billing_enforcement_enabled') === true;
    }

    public function active(): bool
    {
        if (! $this->enabledByEnvironment()) {
            return false;
        }

        $startsAt = BillingSetting::current()->enforcement_starts_at;

        return $startsAt !== null && $startsAt->isPast();
    }

    /**
     * Operational prerequisites to schedule the start of enforcement. Each unmet one is returned with its reason; an
     * empty list means all are met. The Efí homologation itself cannot be checked here: the command asks the operator
     * to confirm it explicitly.
     *
     * @return list<string>
     */
    public function unmetPrerequisites(): array
    {
        $settings = BillingSetting::current();
        $plan = Plan::main();
        $unmet = [];

        if (! $this->enabledByEnvironment()) {
            $unmet[] = 'PLATFORM_BILLING_ENFORCEMENT_ENABLED não está definida como true.';
        }

        if (! $plan->pricesApproved()) {
            $unmet[] = 'Os valores comerciais do plano não estão aprovados no RootAdmin.';
        }

        if (! $settings->isProduction()) {
            $unmet[] = 'O ambiente da Efí não está em produção.';
        }

        if (! $settings->pixConfigured() || ! $settings->chargesConfigured()) {
            $unmet[] = 'As credenciais da Efí (Pix e Cobranças) não estão completas.';
        }

        return $unmet;
    }

    /**
     * Message shown when an operation needs billing in force.
     */
    public static function inactiveMessage(): string
    {
        return 'A cobrança ainda não está em vigor na VetorSmart. O uso continua liberado, e nada será cobrado até o aviso de início.';
    }
}
