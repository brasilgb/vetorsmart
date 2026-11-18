<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingCycle;
use Illuminate\Console\Command;

class RunBillingCycle extends Command
{
    /**
     * @var string
     */
    protected $signature = 'billing:run';

    /**
     * @var string
     */
    protected $description = 'Rotina diária das assinaturas: fim de teste, renovação, carência, suspensão financeira e conciliação com a Efí.';

    public function handle(BillingCycle $cycle): int
    {
        foreach ($cycle->run() as $step => $total) {
            $this->line(sprintf('  %-16s %d', $step, $total));
        }

        return self::SUCCESS;
    }
}
