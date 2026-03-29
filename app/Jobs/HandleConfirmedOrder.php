<?php

namespace App\Jobs;

use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderIntent;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Payments\FeeCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class HandleConfirmedOrder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $orderIntentId)
    {
    }

    public function handle(FeeCalculator $feeCalculator): void
    {
        DB::transaction(function () use ($feeCalculator) {
            /** @var OrderIntent $intent */
            $intent = OrderIntent::query()->lockForUpdate()->with(['occurrence.event', 'discount', 'paymentProvider'])->findOrFail($this->orderIntentId);

            if ($intent->status !== 'confirmed') {
                throw new RuntimeException('Order intent is not confirmed.');
            }

            if (Order::query()->where('order_intent_id', $intent->id)->exists()) {
                return;
            }

            $meta = (array) ($intent->meta ?? []);
            $lines = (array) ($meta['lines'] ?? []);

            $discountAmount = $this->calculateDiscountAmount($intent->discount, (float) $intent->price);

            $methodCode = $intent->paymentProvider?->provider_code ?? 'api';
            $fees = (float) $intent->fees;
            if ($fees <= 0) {
                $fees = $feeCalculator->calculateEventFees(max(0.0, (float) $intent->price - $discountAmount), $methodCode);
            }

            $order = Order::create([
                'number' => $this->newOrderNumber(),
                'claim_code' => $intent->claim_code,
                'order_intent_id' => $intent->id,
                'event_occurrence_id' => $intent->event_occurrence_id,
                'user_id' => $intent->customer_user_id,
                'amount' => max(0.0, (float) $intent->price - $discountAmount + $fees),
                'fees' => $fees,
                'discount_amount' => $discountAmount,
                'currency' => $intent->currency,
                'type' => $meta['type'] ?? 'online',
                'payment_method_id' => $meta['payment_method_id'] ?? null,
                'email' => $intent->customer_email,
                'phone' => $intent->customer_phone,
                'full_name' => $meta['customer_full_name'] ?? null,
                'status' => 'confirmed',
            ]);

            foreach ($lines as $line) {
                $typeId = (int) ($line['ticket_type_id'] ?? 0);
                $qty = (int) ($line['quantity'] ?? 0);
                if ($typeId <= 0 || $qty <= 0) {
                    continue;
                }

                /** @var TicketType $type */
                $type = TicketType::query()->lockForUpdate()->findOrFail($typeId);

                for ($i = 0; $i < $qty; $i++) {
                    Ticket::create([
                        'ticket_key' => Ticket::newTicketKey(),
                        'ticket_number' => $this->newTicketNumber(),
                        'order_id' => $order->id,
                        'ticket_type_id' => $type->id,
                        'event_occurrence_id' => $intent->event_occurrence_id,
                        'price' => $type->price,
                        'status' => 'active',
                        'is_cancellable' => true,
                        'user_id' => $intent->customer_user_id,
                        'email' => $intent->customer_email,
                        'phone' => $intent->customer_phone,
                        'full_name' => $meta['customer_full_name'] ?? null,
                    ]);
                }

                // remaining_quantity follows real_remaining_quantity; real_remaining_quantity was reserved at intent creation.
                $type->remaining_quantity = $type->real_remaining_quantity;
                $type->save();
            }
        });
    }

    private function calculateDiscountAmount(?Discount $discount, float $baseAmount): float
    {
        if (! $discount || ! $discount->isActive()) {
            return 0.0;
        }

        if ($discount->type === 'fixed') {
            return min($baseAmount, (float) $discount->value);
        }

        return min($baseAmount, round($baseAmount * ((float) $discount->value / 100), 2));
    }

    private function newOrderNumber(): string
    {
        do {
            $num = 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(8));
        } while (Order::query()->where('number', $num)->exists());

        return $num;
    }

    private function newTicketNumber(): string
    {
        do {
            $num = 'TCK-' . Str::upper(Str::random(10));
        } while (Ticket::query()->where('ticket_number', $num)->exists());

        return $num;
    }
}

