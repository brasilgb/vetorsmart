<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
