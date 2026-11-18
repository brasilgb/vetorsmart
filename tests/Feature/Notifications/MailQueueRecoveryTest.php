<?php

use App\Notifications\OperationalMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Real database queue and real worker: an unreachable mail server leaves the e-mail in the queue for a later attempt,
 * and the next attempt, with the server back, delivers it.
 */
beforeEach(function () {
    config(['queue.default' => 'database']);
});

function pendingJobs(): int
{
    return DB::table('jobs')->count();
}

test('an e-mail that fails because the mail server is down is retried and delivered when it comes back', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

    Notification::route('mail', 'cliente@example.com')->notify(new OperationalMail('Teste de entrega', 'Olá', ['Linha']));

    expect(pendingJobs())->toBe(1);

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(pendingJobs())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and((int) DB::table('jobs')->value('attempts'))->toBe(1);

    config(['mail.default' => 'array']);
    $this->travel(61)->seconds();

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();

    expect(pendingJobs())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($sent)->toHaveCount(1)
        ->and($sent->first()->getOriginalMessage()->getSubject())->toBe('Teste de entrega');
});

test('an e-mail that keeps failing ends in failed_jobs after the last attempt', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

    Notification::route('mail', 'cliente@example.com')->notify(new OperationalMail('Teste de entrega', 'Olá', ['Linha']));

    foreach ([0, 61, 301] as $seconds) {
        $this->travel($seconds)->seconds();
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);
    }

    expect(pendingJobs())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1);
});
