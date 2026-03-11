<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Event $event,
        public readonly bool $publishNow,
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $eventTitle = $this->event->title ?? 'Votre évènement';
        $statusKey = $this->publishNow ? 'upcoming' : 'saved';

        $subject = $this->publishNow
            ? 'Votre évènement a été publié'
            : 'Votre évènement a été enregistré';

        $lineStatus = $this->publishNow
            ? 'Votre évènement est maintenant publié et visible pour les participants.'
            : 'Votre évènement a été enregistré. Il n’est pas encore publié.';

        $mail = (new MailMessage())
            ->subject($subject . ' - Votix')
            ->greeting('Bonjour,')
            ->line("L’évènement « {$eventTitle} » a été traité par Votix.")
            ->line($lineStatus);

        if ($this->publishNow && $this->event->slug) {
            $mail->line('Vous pouvez le consulter sur la plateforme.');
        }

        return $mail->line('Merci d’utiliser Votix.');
    }
}

