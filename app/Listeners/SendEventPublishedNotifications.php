<?php

namespace App\Listeners;

use App\Events\EventPublished;
use App\Notifications\EventPublishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendEventPublishedNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(EventPublished $event): void
    {
        $user = $event->event->user;

        if (! $user) {
            return;
        }

        $user->notify(new EventPublishedNotification($event->event, $event->publishNow));
    }
}

