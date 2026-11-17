<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Retention of visitor conversations, in simulation: every day it reports what has passed each company's retention
| date and removes nothing. The report goes to the scheduler container's output, so `docker logs` keeps it.
| Automatic removal is a separate decision (config assistant.retention_deletion_enabled and --execute).
*/
Schedule::command('assistant:purge')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->appendOutputTo(is_writable('/proc/1/fd/1') ? '/proc/1/fd/1' : storage_path('logs/assistant-retention.log'));
