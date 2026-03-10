<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\EventOccurrence;
use App\Models\OrderIntent;
use App\Services\Payments\FeeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderIntentsController extends Controller
{
    public function create(Request $request, FeeCalculator $feeCalculator): JsonResponse
    {
        $data = $request->validate([
            'event_occurrence_id' => ['required', 'integer', 'exists:event_occurrences,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'customer_email' => ['nullable', 'email'],
            'customer_phone' => ['nullable', 'string'],
            'delivery_method' => ['nullable', 'string'],
            'discount_code' => ['nullable', 'string'],
            'payment_method_code' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ticket_type_id' => ['required', 'integer', 'exists:ticket_types,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'meta' => ['nullable', 'array'],
        ]);

        /** @var EventOccurrence $occurrence */
        $occurrence = EventOccurrence::query()->with('event')->findOrFail((int) $data['event_occurrence_id']);
        if ($occurrence->status !== 'upcoming') {
            throw ValidationException::withMessages(['occurrence' => 'Occurrence is not available.']);
        }

        $discount = null;
        if (! empty($data['discount_code'])) {
            $code = DiscountCode::query()->where('code', $data['discount_code'])->first();
            if ($code && $code->isActive()) {
                $discount = $code->discount;
            }
        }

        $intent = OrderIntent::create([
            'key' => OrderIntent::newKey(),
            'claim_code' => $data['meta']['claim_code'] ?? null,
            'price' => 0,
            'fees' => 0,
            'currency' => strtoupper($data['currency'] ?? ($occurrence->event?->currency ?? 'XOF')),
            'status' => 'pending',
            'discount_id' => $discount?->id,
            'event_occurrence_id' => $occurrence->id,
            'customer_user_id' => $request->user()?->id,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'delivery_method' => $data['delivery_method'] ?? null,
            'meta' => $data['meta'] ?? [],
        ]);

        $intent->reserveStock($data['lines']);

        $methodCode = $data['payment_method_code'] ?? 'api';
        $intent->fees = $feeCalculator->calculateEventFees((float) $intent->price, $methodCode);
        $intent->save();

        return response()->json([
            'data' => [
                'key' => $intent->key,
                'status' => $intent->status,
                'price' => (float) $intent->price,
                'fees' => (float) $intent->fees,
                'currency' => $intent->currency,
                'expired_at' => $intent->expired_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function checkout(string $key, Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_code' => ['required', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $intent = OrderIntent::query()->where('key', $key)->firstOrFail();
        $provider = $intent->checkout($data['provider_code'], $data['payload'] ?? []);

        return response()->json([
            'data' => [
                'key' => $intent->key,
                'status' => $intent->status,
                'payment_provider' => [
                    'id' => $provider->id,
                    'provider_code' => $provider->provider_code,
                    'refs' => $provider->only(['wave_checkout_id', 'djamo_charge_id', 'paystack_reference', 'hub2_reference', 'intouch_reference']),
                    'meta' => $provider->meta,
                ],
            ],
        ]);
    }

    public function verify(string $key): JsonResponse
    {
        $intent = OrderIntent::query()->where('key', $key)->firstOrFail();
        $paid = $intent->verify();

        return response()->json([
            'data' => [
                'key' => $intent->key,
                'status' => $intent->fresh()->status,
                'paid' => $paid,
            ],
        ]);
    }
}

