<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class MarkHomologTenant extends Command
{
    /**
     * @var string
     */
    protected $signature = 'homolog:mark {tenant : ID da empresa}';

    /**
     * @var string
     */
    protected $description = 'Marca uma empresa como homologação (fora dos indicadores e incluída no homolog:purge).';

    /**
     * The mark can only be set from the terminal, after the operator confirms the company shown. It is never removed
     * here: a company marked by mistake is unmarked in the database, deliberately, before any purge.
     */
    public function handle(AuditLogger $auditLogger): int
    {
        $tenant = Tenant::query()->find((int) $this->argument('tenant'));

        if ($tenant === null) {
            $this->components->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        if ($tenant->isHomolog()) {
            $this->components->info(sprintf('A empresa #%d (%s) já está marcada como homologação.', $tenant->getKey(), $tenant->name));

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('A marcação exige terminal interativo para a confirmação.');

            return self::FAILURE;
        }

        $this->components->warn(sprintf('Empresa #%d: %s (%s). Os dados dela serão removidos pelo homolog:purge.', $tenant->getKey(), $tenant->name, $tenant->slug));

        if (! $this->confirm('Marcar esta empresa como homologação?')) {
            $this->components->error('Nada foi alterado.');

            return self::FAILURE;
        }

        $tenant->forceFill(['is_homolog' => true])->save();

        $auditLogger->record('platform.tenant_marked_homolog', $tenant, $tenant);

        $this->components->info('Empresa marcada como homologação.');

        return self::SUCCESS;
    }
}
