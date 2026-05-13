<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $order = $this->order->loadMissing(['occurrence.event', 'tickets.ticketType']);
        $eventTitle = $order->occurrence?->event?->title ?? 'Votix Event';
        $ticketCount = $order->tickets->count();
        $currency = strtoupper((string) ($order->currency ?? 'XOF'));
        $displayCurrency = $currency === 'XOF' ? 'FCFA' : $currency;
        $amount = number_format((float) ($order->amount ?? 0), 0, ',', ' ');

        $frontendUrl = $this->resolveFrontendBaseUrl();
        $logoUrl = $frontendUrl . '/images/logos/black.jpeg';
        $dashboardUrl = $frontendUrl . '/fr/tableau-de-bord/mes-billets';

        return (new MailMessage())
            ->subject('Confirmation d’achat - ' . ($order->number ?? 'Commande') . ' - Votix')
            ->view('emails.order-confirmed', [
                'subject' => 'Confirmation d’achat',
                'headline' => 'Vos billets sont confirmés',
                'intro' => 'Merci pour votre achat sur Votix. Votre commande a bien été enregistrée.',
                'eventTitle' => $eventTitle,
                'orderNumber' => $order->number,
                'ticketCount' => $ticketCount,
                'amount' => $amount,
                'displayCurrency' => $displayCurrency,
                'claimCode' => $order->claim_code,
                'logoUrl' => $logoUrl,
                'actionUrl' => $dashboardUrl,
                'actionText' => 'Voir mes billets',
                'footerNote' => 'Conservez cet e-mail : il contient les informations de votre commande.',
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

