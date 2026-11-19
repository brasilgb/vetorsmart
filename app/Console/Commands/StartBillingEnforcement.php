<?php

namespace App\Console\Commands;

use App\Enums\BillingSource;
use App\Enums\SubscriptionStatus;
use App\Models\BillingSetting;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\Billing\BillingEnforcement;
use App\Services\Billing\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Explicit, audited transition into billing enforcement. Preview by default; --execute applies it after a typed
 * confirmation in an interactive terminal. It refuses to run while any prerequisite is unmet (environment switch,
 * approved prices, Efí in production with full credentials) and asks the operator to confirm the Efí homologation.
 *
 * Nothing is retroactive: every subscription still in trial, or whose trial ended during the period without billing,
 * gets its trial extended to the start date plus the adaptation days, so no company is charged or suspended for the
 * time billing was not in force. Companies without a subscription are only included with --include-legacy, which
 * starts their trial with the same end. Every change is recorded in the subscription history and in the audit log.
 */
class StartBillingEnforcement extends Command
{
    private const string CONFIRMATION = 'INICIAR COBRANCA';

    /**
     * @var string
     */
    protected $signature = 'billing:start-enforcement
        {--starts-at= : Data de início da cobrança (Y-m-d), no futuro}
        {--adaptation-days=0 : Dias de adaptação depois do início, antes do primeiro vencimento}
        {--include-legacy : Inicia o teste também das empresas sem assinatura (anteriores à cobrança)}
        {--execute : Aplica a transição após confirmação digitada}';

    /**
     * @var string
     */
    protected $description = 'Agenda o início da cobrança em vigor, com transição auditável e sem efeito retroativo. Prévia por padrão.';

    public function handle(BillingEnforcement $enforcement, SubscriptionService $subscriptions, AuditLogger $auditLogger): int
    {
        $startsAt = $this->startDate();
        $adaptationDays = max(0, (int) $this->option('adaptation-days'));

        if ($startsAt === null) {
            $this->components->error('Informe --starts-at=AAAA-MM-DD com uma data futura.');

            return self::INVALID;
        }

        $firstDue = $startsAt->addDays($adaptationDays);
        $plan = $this->plan($firstDue);

        $this->components->info('Transição para a cobrança em vigor');
        $this->components->twoColumnDetail('Início da cobrança', $startsAt->format('d/m/Y'));
        $this->components->twoColumnDetail('Fim mínimo dos testes (primeiro vencimento)', $firstDue->format('d/m/Y'));
        foreach ($plan as $label => $total) {
            $this->components->twoColumnDetail($label, (string) $total);
        }

        $unmet = $enforcement->unmetPrerequisites();

        if ($unmet !== []) {
            $this->components->error('Pré-requisitos não atendidos; nada foi alterado:');
            $this->components->bulletList($unmet);

            return self::FAILURE;
        }

        if (! $this->option('execute')) {
            $this->components->warn('Prévia apenas. Para aplicar, execute novamente com --execute.');

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('A transição exige terminal interativo para a confirmação.');

            return self::FAILURE;
        }

        if (! $this->confirm('A homologação da Efí foi concluída e aprovada?')) {
            $this->components->error('Transição cancelada: a homologação da Efí é obrigatória.');

            return self::FAILURE;
        }

        if ((string) $this->ask('Digite exatamente "'.self::CONFIRMATION.'" para confirmar') !== self::CONFIRMATION) {
            $this->components->error('Confirmação incorreta; nada foi alterado.');

            return self::FAILURE;
        }

        $applied = DB::transaction(function () use ($startsAt, $firstDue, $subscriptions, $auditLogger): array {
            $counts = ['trials_extended' => 0, 'legacy_started' => 0];
            $note = 'Transição para a cobrança em vigor: teste até '.$firstDue->format('d/m/Y').', sem efeito retroativo.';

            Subscription::query()->where('status', SubscriptionStatus::Trialing->value)->lockForUpdate()->get()
                ->each(function (Subscription $subscription) use ($firstDue, $note, &$counts): void {
                    if ($subscription->trial_ends_at !== null && $subscription->trial_ends_at->gte($firstDue)) {
                        return;
                    }

                    $subscription->forceFill(['trial_ends_at' => $firstDue])->save();
                    $this->event($subscription, $note);
                    $counts['trials_extended']++;
                });

            if ($this->option('include-legacy')) {
                Tenant::query()->whereNotIn('id', Subscription::query()->select('tenant_id'))->get()->each(function (Tenant $tenant) use ($subscriptions, $firstDue, $note, &$counts): void {
                    $subscription = $subscriptions->startTrial($tenant, BillingSource::System);
                    $subscription->forceFill(['trial_ends_at' => $firstDue])->save();
                    $this->event($subscription, $note);
                    $counts['legacy_started']++;
                });
            }

            $settings = BillingSetting::current();
            $settings->forceFill(['enforcement_starts_at' => $startsAt])->save();
            $auditLogger->record('billing.enforcement_scheduled', null, $settings, [
                'starts_at' => $startsAt->toDateString(),
                'first_due' => $firstDue->toDateString(),
                'include_legacy' => (bool) $this->option('include-legacy'),
                ...$counts,
            ]);

            return $counts;
        });

        $this->components->info(sprintf('Cobrança em vigor a partir de %s. Testes prorrogados: %d. Empresas antigas incluídas: %d.', $startsAt->format('d/m/Y'), $applied['trials_extended'], $applied['legacy_started']));

        return self::SUCCESS;
    }

    private function startDate(): ?CarbonImmutable
    {
        $value = (string) $this->option('starts-at');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::parse($value)->startOfDay();

        return $date->isFuture() ? $date : null;
    }

    /**
     * What the transition would do, for the preview.
     *
     * @return array<string, int>
     */
    private function plan(CarbonImmutable $firstDue): array
    {
        return [
            'Assinaturas em teste a prorrogar' => Subscription::query()->where('status', SubscriptionStatus::Trialing->value)
                ->where(fn ($query) => $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<', $firstDue))->count(),
            'Assinaturas ativas (mantidas)' => Subscription::query()->where('status', SubscriptionStatus::Active->value)->count(),
            'Assinaturas em carência, suspensas ou canceladas (revisão manual)' => Subscription::query()->whereIn('status', [SubscriptionStatus::PastDue->value, SubscriptionStatus::Suspended->value, SubscriptionStatus::Cancelled->value])->count(),
            'Empresas sem assinatura'.($this->option('include-legacy') ? ' (incluídas)' : ' (não incluídas)') => Tenant::query()->whereNotIn('id', Subscription::query()->select('tenant_id'))->count(),
        ];
    }

    private function event(Subscription $subscription, string $note): void
    {
        $event = new SubscriptionEvent;
        $event->forceFill([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'type' => 'enforcement_transition',
            'from_status' => $subscription->status->value,
            'to_status' => $subscription->status->value,
            'source' => BillingSource::System,
            'note' => $note,
        ])->save();
    }
}
