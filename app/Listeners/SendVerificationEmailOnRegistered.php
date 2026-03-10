<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendVerificationEmailOnRegistered implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    /**
     * Envoi de l'email de vérification en arrière-plan (queue).
     */
    public function handle(Registered $event): void
    {
        $user = $event->user;
        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }
    }
}
