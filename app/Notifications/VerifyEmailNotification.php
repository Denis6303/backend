<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Vérifiez votre adresse e-mail')
            ->greeting('Bienvenue sur Votix')
            ->line('Merci de vous être inscrit sur Votix.')
            ->line('Pour activer votre compte, cliquez sur le bouton ci-dessous afin de vérifier votre adresse e-mail.')
            ->action('Vérifier mon adresse e-mail', $url)
            ->line('Si vous n\'êtes pas à l\'origine de cette inscription, vous pouvez ignorer cet e-mail.');
    }
}

