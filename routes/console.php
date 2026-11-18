<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Retention of visitor conversations, in simulation: every day it reports what has passed each company's retention
| date and removes nothing. The report is appended to storage/logs/assistant-retention.log, which in production is
| a persistent volume, so it survives container replacement.
| Automatic removal is a separate decision (config assistant.retention_deletion_enabled and --execute).
*/
Schedule::command('assistant:purge')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/assistant-retention.log'));

/*
| Billing: trial end, renewal charges, grace period, financial suspension and reconciliation with Efí. Every step is
| idempotent, so a missed day is caught up on the next run.
*/
Schedule::command('billing:run')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing.log'));
