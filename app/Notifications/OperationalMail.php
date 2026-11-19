<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * An operational e-mail (to the company's team or to its customer), sent through the queue only after the database
 * transaction that produced it is committed. A rolled-back operation never sends anything.
 */
class OperationalMail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var int
     */
    public $tries = 3;

    /**
     * Key of the operational notice (notification_deliveries) this e-mail belongs to, so its real outcome is recorded.
     */
    public ?string $deliveryKey = null;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $subjectLine,
        public string $heading,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public ?string $senderName = null,
        public ?string $replyTo = null,
        public ?string $closing = null,
    ) {
        $this->afterCommit();
    }

    /**
     * The last attempt failed (the mail server kept refusing): the notice records one more e-mail failed. Never counted
     * as delivered. The job itself stays in failed_jobs and can be retried with queue:retry.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->deliveryKey !== null) {
            DB::table('notification_deliveries')->where('dedupe_key', $this->deliveryKey)->increment('mail_failed', 1, ['updated_at' => now()]);
        }
    }

    /**
     * Seconds to wait between attempts.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subjectLine)
            ->greeting($this->heading)
            ->salutation($this->closing ?? 'Equipe VetorSmart');

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        if ($this->actionText !== null && $this->actionUrl !== null) {
            $message->action($this->actionText, $this->actionUrl);
        }

        if ($this->senderName !== null) {
            $message->from((string) config('mail.from.address'), $this->senderName);
        }

        if ($this->replyTo !== null) {
            $message->replyTo($this->replyTo);
        }

        return $message;
    }
}
