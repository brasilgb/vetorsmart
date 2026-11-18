<?php

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Models\Plan;

/**
 * The public offer of the plan: the three cycles with total, monthly equivalent and savings against the monthly
 * cycle, the users and AI credits included and the services actually available in the panel. Used by Minha
 * Assinatura and kept ready for the future landing page, so prices are presented in one place only.
 */
class PlanCatalog
{
    /**
     * Services available in the panel today. Only what exists is listed.
     *
     * @var list<string>
     */
    public const array BENEFITS = [
        'Cadastro de clientes e histórico de atendimentos',
        'Solicitações de serviço com triagem e acompanhamento',
        'Agenda com disponibilidade dos profissionais e bloqueios',
        'Catálogo de serviços e profissionais',
        'Site da empresa com subdomínio e domínio próprio',
        'Atendimento virtual com agendamento pelo site',
        'Central de notificações e avisos por e-mail',
        'Equipe com perfis de proprietário, administrador e operador',
    ];

    /**
     * @return array{name: string, description: string|null, trial_days: int, included_users: int|null, extra_user_price_cents: int|null, ai_monthly_credits: int|null, promotion_percent: int, promotion_until: string|null, prices_approved: bool, cycles: list<array{interval: string, label: string, months: int, total_cents: int, monthly_equivalent_cents: int, savings_percent: int}>, benefits: list<string>}
     */
    public function offer(?Plan $plan = null): array
    {
        $plan ??= Plan::main();
        $monthly = $plan->priceFor(BillingInterval::Monthly);
        $cycles = [];

        foreach (BillingInterval::cases() as $interval) {
            $total = $plan->priceFor($interval);

            if ($total === null) {
                continue;
            }

            $equivalent = (int) round($total / $interval->months());

            $cycles[] = [
                'interval' => $interval->value,
                'label' => $interval->label(),
                'months' => $interval->months(),
                'total_cents' => $total,
                'monthly_equivalent_cents' => $equivalent,
                'savings_percent' => $monthly === null || $interval === BillingInterval::Monthly ? 0 : max(0, (int) round(100 - ($equivalent * 100 / $monthly))),
            ];
        }

        return [
            'name' => $plan->name,
            'description' => $plan->description,
            'trial_days' => $plan->trial_days,
            'included_users' => $plan->included_users,
            'extra_user_price_cents' => $plan->extra_user_price_cents,
            'ai_monthly_credits' => $plan->ai_monthly_credits,
            'promotion_percent' => $plan->activePromotionPercent(),
            'promotion_until' => $plan->activePromotionPercent() > 0 ? $plan->promotional_until?->format('d/m/Y') : null,
            'prices_approved' => $plan->pricesApproved(),
            'cycles' => $cycles,
            'benefits' => self::BENEFITS,
        ];
    }
}
