<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The e-mail confirmation link, sent through the queue. A slow or unavailable mail server no longer turns the
 * registration, the team invitation or the resend button into an error page: the worker retries, and a delivery
 * that still fails is kept in failed_jobs for the operator.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * @var int
     */
    public $tries = 3;

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
     * The message in Portuguese, with the VetorSmart layout.
     *
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirme seu e-mail · VetorSmart')
            ->greeting('Confirme seu e-mail')
            ->line('Use o botão abaixo para confirmar o endereço e liberar o acesso ao painel da VetorSmart.')
            ->action('Confirmar e-mail', $url)
            ->line('O link vale por '.config('auth.verification.expire', 60).' minutos. Se você não criou uma conta, ignore esta mensagem.')
            ->salutation('Equipe VetorSmart');
    }
}
