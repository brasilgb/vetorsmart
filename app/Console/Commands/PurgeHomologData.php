<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\HomologPurgeService;
use Illuminate\Console\Command;

class PurgeHomologData extends Command
{
    /**
     * The phrase the operator must type before any removal.
     */
    public const string CONFIRMATION = 'EXCLUIR HOMOLOG';

    /**
     * @var string
     */
    protected $signature = 'homolog:purge {--execute : Remove os registros após confirmação digitada}';

    /**
     * @var string
     */
    protected $description = 'Prévia (padrão) e remoção controlada apenas de registros [HOMOLOG].';

    public function handle(HomologPurgeService $purge, AuditLogger $auditLogger): int
    {
        $plan = $purge->plan();

        $this->components->info('Registros de homologação identificados (nenhum alterado ainda):');

        foreach ($plan['counts'] as $table => $total) {
            $this->line(sprintf('  %-22s %d', $table, $total));
        }

        if (! $this->option('execute')) {
            $this->components->warn('Prévia apenas. Para remover, execute novamente com --execute.');

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('A remoção exige terminal interativo para a confirmação.');

            return self::FAILURE;
        }

        if ($plan['tenant_ids'] === [] && $plan['user_ids'] === []) {
            $this->components->info('Nada a remover.');

            return self::SUCCESS;
        }

        $typed = (string) $this->ask('Digite exatamente "'.self::CONFIRMATION.'" para confirmar');

        if ($typed !== self::CONFIRMATION) {
            $this->components->error('Confirmação não confere. Nada foi removido.');

            return self::FAILURE;
        }

        $removed = $purge->execute();

        $auditLogger->record('platform.homolog_purged', null, null, $removed);

        $this->components->info(sprintf('Removidos: %d empresa(s) e %d usuário(s) de homologação.', $removed['tenants'], $removed['users']));

        return self::SUCCESS;
    }
}
