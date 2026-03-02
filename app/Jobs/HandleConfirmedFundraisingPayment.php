<?php

namespace App\Jobs;

use App\Models\FundraisingContribution;
use App\Models\FundraisingPaymentIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HandleConfirmedFundraisingPayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $paymentIntentId)
    {
    }

    public function handle(): void
    {
        DB::transaction(function () {
            /** @var FundraisingPaymentIntent $intent */
            $intent = FundraisingPaymentIntent::query()
                ->lockForUpdate()
                ->with(['fundraising'])
                ->findOrFail($this->paymentIntentId);

            if ($intent->status !== 'paid') {
                throw new RuntimeException('Fundraising payment intent is not paid.');
            }

            if ($intent->contribution) {
                return;
            }

            $meta = (array) ($intent->meta ?? []);

            $contribution = FundraisingContribution::create([
                'fundraising_id' => $intent->fundraising_id,
                'payment_intent_id' => $intent->id,
                'payment_method_id' => $intent->payment_method_id,
                'payer_user_id' => $intent->user_id,
                'email' => $intent->customer_email,
                'phone' => $intent->customer_phone,
                'name' => $meta['name'] ?? null,
                'amount' => $intent->amount,
                'fees' => $intent->fees,
                'is_amount_visible' => (bool) ($meta['is_amount_visible'] ?? true),
                'message' => $meta['message'] ?? null,
                'paid_at' => now(),
            ]);

            $fundraising = $intent->fundraising()->lockForUpdate()->firstOrFail();

            // Business rule: +90% of the amount goes to current_amount
            $fundraising->current_amount = (float) $fundraising->current_amount + round(((float) $contribution->amount) * 0.9, 2);
            $fundraising->save();
        });
    }
}

