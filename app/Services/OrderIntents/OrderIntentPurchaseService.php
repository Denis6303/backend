<?php

namespace App\Services\OrderIntents;

use App\Models\DiscountCode;
use App\Models\EventOccurrence;
use App\Models\OrderIntent;
use App\Models\TemporaryTicketReservation;
use App\Models\TicketType;
use App\Exceptions\ApiCodes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;
use Symfony\Component\HttpFoundation\Response;

class OrderIntentPurchaseService
{
    /**
     * @param  array<string,mixed>  $validated
     */
    public function create(array $validated): OrderIntent
    {
        if (($validated['type'] ?? 'online') !== 'online') {
            throw ValidationException::withMessages([
                'type' => [__('messages.order_intent.type_not_supported')],
            ]);
        }

        $this->assertCustomerMatchesAuth((int) $validated['customer_id']);

        $occurrence = EventOccurrence::query()
            ->with('event')
            ->findOrFail((int) $validated['event_occurrence_id']);

        if ($occurrence->status !== EventOccurrence::STATUS_UPCOMING) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => [__('messages.order_intent.occurrence_not_available')],
            ]);
        }

        $event = $occurrence->event;
        if (! $event) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => [__('messages.order_intent.occurrence_not_available')],
            ]);
        }

        $lines = $this->normalizeTicketLines($validated['tickets'], $occurrence->id);
        $this->assertOnlineLimits($lines);

        $discountCode = null;
        if (! empty($validated['coupon_code'])) {
            $discountCode = $this->resolveCoupon((string) $validated['coupon_code'], $occurrence->id);
        }

        $ttl = (int) config('custom.order.expiration_minutes', 15);

        return DB::transaction(function () use ($validated, $occurrence, $event, $lines, $discountCode, $ttl) {
            $meta = [
                'type' => $validated['type'],
                'customer_full_name' => $validated['customer_full_name'] ?? null,
            ];
            if (! empty($validated['coupon_code'])) {
                $meta['coupon_code'] = $validated['coupon_code'];
            }

            $intent = OrderIntent::create([
                'key' => OrderIntent::newKey(),
                'claim_code' => strtoupper(bin2hex(random_bytes(4))),
                'price' => 0,
                'fees' => 0,
                'currency' => $event->currency,
                'status' => 'pending',
                'discount_id' => $discountCode?->discount_id,
                'event_occurrence_id' => $occurrence->id,
                'customer_user_id' => (int) $validated['customer_id'],
                'customer_email' => $validated['email'] ?? null,
                'customer_phone' => $validated['phone'] ?? null,
                'delivery_method' => $validated['delivery_method'],
                'meta' => $meta,
            ]);

            $intent->reserveStock($lines, $ttl);

            foreach ($lines as $line) {
                TemporaryTicketReservation::create([
                    'order_intent_id' => $intent->id,
                    'ticket_type_id' => $line['ticket_type_id'],
                    'quantity' => $line['quantity'],
                    'expires_at' => $intent->expired_at,
                ]);
            }

            if ($discountCode) {
                $discountCode->increment('uses_count');
            }

            $occurrence->increment('nb_visites');

            return $intent->fresh(['occurrence.event', 'discount', 'temporaryReservations.ticketType']);
        });
    }

    public function releaseStockAndExpire(OrderIntent $intent): void
    {
        if ($intent->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($intent) {
            $lines = (array) (($intent->meta ?? [])['lines'] ?? []);

            foreach ($lines as $line) {
                $typeId = (int) ($line['ticket_type_id'] ?? 0);
                $qty = (int) ($line['quantity'] ?? 0);
                if ($typeId <= 0 || $qty <= 0) {
                    continue;
                }

                /** @var TicketType|null $type */
                $type = TicketType::query()->lockForUpdate()->find($typeId);
                if (! $type) {
                    continue;
                }

                $type->real_remaining_quantity += $qty;
                $type->remaining_quantity = min($type->remaining_quantity, $type->real_remaining_quantity);
                $type->save();
            }

            $intent->temporaryReservations()->delete();

            $intent->status = 'expired';
            $intent->save();
        });
    }

    /**
     * @param  array<string|int, mixed>  $tickets
     * @return array<int, array{ticket_type_id:int, quantity:int}>
     */
    public function normalizeTicketLines(array $tickets, int $occurrenceId): array
    {
        $lines = [];

        foreach ($tickets as $ticketTypeId => $quantity) {
            if (is_array($quantity)) {
                throw ValidationException::withMessages([
                    'tickets' => [__('messages.order_intent.invalid_tickets_shape')],
                ]);
            }

            $id = (int) $ticketTypeId;
            $qty = (int) $quantity;

            if ($id <= 0 || $qty <= 0) {
                continue;
            }

            $lines[] = [
                'ticket_type_id' => $id,
                'quantity' => $qty,
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'tickets' => [__('validation.required', ['attribute' => 'tickets'])],
            ]);
        }

        foreach ($lines as $line) {
            $type = TicketType::query()
                ->where('id', $line['ticket_type_id'])
                ->where('event_occurrence_id', $occurrenceId)
                ->first();

            if (! $type) {
                throw ValidationException::withMessages([
                    'tickets' => [__('messages.order_intent.invalid_ticket_type')],
                ]);
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, array{ticket_type_id:int, quantity:int}>  $lines
     */
    protected function assertOnlineLimits(array $lines): void
    {
        $max = (int) config('custom.order.max_tickets_per_type_online', 10);

        foreach ($lines as $line) {
            if ($line['quantity'] > $max) {
                throw ValidationException::withMessages([
                    'tickets' => [__('messages.order_intent.max_per_type', ['max' => $max])],
                ]);
            }
        }
    }

    protected function resolveCoupon(string $code, int $occurrenceId): DiscountCode
    {
        $discountCode = DiscountCode::query()
            ->where('code', $code)
            ->whereHas('discount', fn ($q) => $q->where('event_occurrence_id', $occurrenceId))
            ->first();

        if (! $discountCode || ! $discountCode->isActive()) {
            throw ValidationException::withMessages([
                'coupon_code' => [__('messages.order_intent.invalid_coupon')],
            ]);
        }

        return $discountCode;
    }

    protected function assertCustomerMatchesAuth(int $customerId): void
    {
        $user = Auth::guard('api')->user();
        if ($user && (int) $user->id !== $customerId) {
            throw ValidationException::withMessages([
                'customer_id' => [__('messages.order_intent.customer_mismatch')],
            ]);
        }
    }

    /**
     * Client OAuth (no user): require customer_id query/body to match intent.
     */
    public function assertIntentAccessible(OrderIntent $intent, ?int $customerIdFromRequest = null): void
    {
        $user = Auth::guard('api')->user();

        if ($user) {
            if ((int) $intent->customer_user_id !== (int) $user->id) {
                throw new HttpResponseException(
                    ResponseBuilder::asError(ApiCodes::FORBIDDEN)
                        ->withHttpCode(Response::HTTP_FORBIDDEN)
                        ->withMessage(__('messages.order_intent.forbidden'))
                        ->build()
                );
            }

            return;
        }

        if ($customerIdFromRequest === null || (int) $intent->customer_user_id !== (int) $customerIdFromRequest) {
            throw ValidationException::withMessages([
                'customer_id' => [__('messages.order_intent.customer_id_required')],
            ]);
        }
    }

    public static function mapPaymentMethodToProviderCode(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'yass', 'yass-deposit' => 'yass',
            'flooz', 'flooz-deposit' => 'flooz',
            default => 'fake',
        };
    }

    public function isDepositPayment(string $paymentMethod): bool
    {
        return in_array($paymentMethod, ['yass-deposit', 'flooz-deposit'], true);
    }
}
