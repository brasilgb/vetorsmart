<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Websites\TenantSubdomainService;
use Illuminate\Console\Command;

/**
 * Gives a platform subdomain to every company that has none (companies registered before WILDCARD-01). Preview by
 * default; only --execute writes, and it only adds rows: no existing subdomain or domain is changed.
 */
class AssignTenantSubdomains extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sites:assign-subdomains {--execute : Cria os subdomínios listados na prévia}';

    /**
     * @var string
     */
    protected $description = 'Prévia (padrão) e criação dos subdomínios das empresas que ainda não têm um.';

    public function handle(TenantSubdomainService $subdomains): int
    {
        $missing = Tenant::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (Tenant $tenant): bool => $subdomains->current($tenant) === null);

        if ($missing->isEmpty()) {
            $this->components->info('Todas as empresas já têm subdomínio.');

            return self::SUCCESS;
        }

        if (! $this->option('execute')) {
            $this->components->info($missing->count().' empresa(s) sem subdomínio:');

            foreach ($missing as $tenant) {
                $this->line(sprintf('  #%d  %s', $tenant->id, $tenant->slug));
            }

            $this->components->warn('Prévia apenas. Para criar, execute novamente com --execute.');

            return self::SUCCESS;
        }

        foreach ($missing as $tenant) {
            $domain = $subdomains->assign($tenant);
            $this->line(sprintf('  #%d  %s', $tenant->id, $domain->hostname));
        }

        $this->components->info($missing->count().' subdomínio(s) criado(s).');

        return self::SUCCESS;
    }
}
