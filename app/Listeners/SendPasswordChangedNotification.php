<?php

namespace App\Listeners;

use App\Notifications\PasswordChangedNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPasswordChangedNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    /**
     * Envoi de l'email "mot de passe modifié" en arrière-plan (queue).
     */
    public function handle(PasswordReset $event): void
    {
        $event->user->notify(new PasswordChangedNotification());
    }
}
