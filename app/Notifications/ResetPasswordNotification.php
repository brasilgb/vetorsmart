<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password reset link, sent through the queue, so an unavailable mail server never turns the request into an
 * error page. The link expires as configured in auth.passwords, counted from the request, not from the delivery.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
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
            ->subject('Redefinição de senha · VetorSmart')
            ->greeting('Redefinição de senha')
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Criar nova senha', $url)
            ->line('O link vale por '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos.')
            ->line('Se você não pediu a redefinição, ignore esta mensagem: sua senha continua a mesma.')
            ->salutation('Equipe VetorSmart');
    }
}
