<?php

namespace App\Jobs;

use App\Models\Fundraising;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class HandleFundraisingCancellationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $fundraisingId, public readonly ?string $reason = null)
    {
    }

    public function handle(): void
    {
        DB::transaction(function () {
            /** @var Fundraising $fundraising */
            $fundraising = Fundraising::query()->lockForUpdate()->findOrFail($this->fundraisingId);

            $fundraising->stop();

            $fundraising->contributions()
                ->whereNotNull('paid_at')
                ->get()
                ->each(fn ($c) => $c->cancel($this->reason ?? 'fundraising_cancelled'));
        });
    }
}

