<?php

namespace App\Jobs;

use App\Models\TicketTypePromotion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PromotionNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $promotionId)
    {
    }

    public function handle(): void
    {
        // Placeholder: integrate notification channels (push/email/sms) based on subscriptions.
        TicketTypePromotion::query()->find($this->promotionId);
    }
}

