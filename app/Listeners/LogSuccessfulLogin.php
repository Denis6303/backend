<?php

namespace App\Listeners;

use App\Events\UserAuthenticated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogSuccessfulLogin implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    /**
     * Met à jour last_login_at en arrière-plan (queue).
     */
    public function handle(UserAuthenticated $event): void
    {
        $event->user->update(['last_login_at' => now()]);
    }
}
