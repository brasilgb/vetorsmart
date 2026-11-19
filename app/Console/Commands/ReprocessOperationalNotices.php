<?php

namespace App\Console\Commands;

use App\Services\OperationalNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Process again the operational notices whose processing failed (status "failed" in notification_deliveries). Each
 * notice is claimed in a single conditional update, so running the command twice, or while a new event arrives, never
 * notifies twice. A notice that cannot be rebuilt (subject removed, appointment moved to another time) is marked
 * "skipped". E-mails that the mail server refused are retried by the queue itself (queue:retry), not here.
 */
class ReprocessOperationalNotices extends Command
{
    /**
     * @var string
     */
    protected $signature = 'notifications:reprocess {--limit=100 : Máximo de avisos processados nesta execução}';

    /**
     * @var string
     */
    protected $description = 'Processa novamente avisos operacionais que falharam, sem duplicar.';

    public function handle(OperationalNotifier $notifier): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));

        $failed = DB::table('notification_deliveries')
            ->where('status', 'failed')
            ->whereIn('event', OperationalNotifier::REPROCESSABLE)
            ->orderBy('id')
            ->limit($limit)
            ->get(['dedupe_key', 'event', 'subject_id', 'actor_id']);

        $rebuilt = 0;

        foreach ($failed as $delivery) {
            $rebuilt += $notifier->reprocess((string) $delivery->dedupe_key, $delivery->event === null ? null : (string) $delivery->event, $delivery->subject_id === null ? null : (int) $delivery->subject_id, $delivery->actor_id === null ? null : (int) $delivery->actor_id) ? 1 : 0;
        }

        $skipped = $failed->count() - $rebuilt;
        $stillFailed = DB::table('notification_deliveries')->where('status', 'failed')->count();

        $this->info("Avisos reprocessados: {$rebuilt}. Descartados (não reconstruíveis): {$skipped}. Ainda com falha: {$stillFailed}.");

        return self::SUCCESS;
    }
}
