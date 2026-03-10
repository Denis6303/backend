<?php

namespace App\Jobs;

use App\Models\EventOccurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class HandleEventOccurrenceCancellation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $occurrenceId, public readonly ?string $reason = null)
    {
    }

    public function handle(): void
    {
        DB::transaction(function () {
            /** @var EventOccurrence $occurrence */
            $occurrence = EventOccurrence::query()->lockForUpdate()->findOrFail($this->occurrenceId);

            $occurrence->tickets()
                ->whereIn('status', ['active', 'validated', 'transferred'])
                ->get()
                ->each(fn ($t) => $t->cancelAndRefund($this->reason ?? 'event_cancelled'));

            $occurrence->orders()
                ->where('status', 'confirmed')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });
    }
}

