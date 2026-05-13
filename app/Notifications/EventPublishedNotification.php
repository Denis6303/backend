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
        $subject = $this->publishNow
            ? 'Votre évènement a été publié'
            : 'Votre évènement a été enregistré';

        $statusText = $this->publishNow
            ? 'Votre évènement est maintenant publié et visible pour les participants.'
            : 'Votre évènement a été enregistré. Il n’est pas encore publié.';

        $frontendUrl = $this->resolveFrontendBaseUrl();
        $logoUrl = $frontendUrl . '/images/logos/black.jpeg';
        $eventUrl = $this->publishNow && $this->event->slug
            ? $frontendUrl . '/fr/evenements/' . $this->event->slug
            : null;

        return (new MailMessage())
            ->subject($subject . ' - Votix')
            ->view('emails.event-published', [
                'subject' => $subject,
                'headline' => $this->publishNow ? 'Évènement publié avec succès' : 'Brouillon enregistré',
                'intro' => "L’évènement « {$eventTitle} » a été traité par Votix.",
                'eventTitle' => $eventTitle,
                'statusText' => $statusText,
                'eventUrl' => $eventUrl,
                'logoUrl' => $logoUrl,
                'actionUrl' => $eventUrl ?: ($frontendUrl . '/fr/tableau-de-bord/mes-evenements'),
                'actionText' => $this->publishNow ? 'Voir la page publique' : 'Gérer mes évènements',
                'footerNote' => 'Merci d’utiliser Votix pour vos évènements.',
            ]);
    }

    private function resolveFrontendBaseUrl(): string
    {
        $configured = trim((string) config('app.frontend_url', ''));
        if ($configured !== '' && $configured !== 'http://localhost') {
            return rtrim($configured, '/');
        }

        if (app()->environment('local', 'development', 'testing')) {
            return 'http://127.0.0.1:4000';
        }

        return 'https://votxevent.com';
    }
}

