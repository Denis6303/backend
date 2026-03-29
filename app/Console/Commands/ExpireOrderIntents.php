<?php

namespace App\Console\Commands;

use App\Models\OrderIntent;
use App\Services\OrderIntents\OrderIntentPurchaseService;
use Illuminate\Console\Command;

class ExpireOrderIntents extends Command
{
    protected $signature = 'order-intents:expire';

    protected $description = 'Expire pending order intents past expired_at and release temporary stock';

    public function handle(OrderIntentPurchaseService $service): int
    {
        $count = 0;

        OrderIntent::query()
            ->where('status', 'pending')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->chunkById(100, function ($intents) use ($service, &$count) {
                foreach ($intents as $intent) {
                    $service->releaseStockAndExpire($intent);
                    $count++;
                }
            });

        $this->info("Expired {$count} order intent(s).");

        return self::SUCCESS;
    }
}
