<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Fundraising;
use App\Models\FundraisingPaymentIntent;
use App\Services\Payments\FeeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FundraisingPaymentIntentsController extends Controller
{
    public function create(Request $request, FeeCalculator $feeCalculator): JsonResponse
    {
        $data = $request->validate([
            'fundraising_id' => ['required', 'integer', 'exists:fundraisings,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'customer_email' => ['nullable', 'email'],
            'customer_phone' => ['nullable', 'string'],
            'payment_method_code' => ['nullable', 'string'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'meta' => ['nullable', 'array'],
        ]);

        /** @var Fundraising $fundraising */
        $fundraising = Fundraising::query()->findOrFail((int) $data['fundraising_id']);
        if (! $fundraising->acceptsContributions()) {
            throw ValidationException::withMessages(['fundraising' => 'Fundraising does not accept contributions.']);
        }

        $methodCode = $data['payment_method_code'] ?? 'api';
        $amount = (float) $data['amount'];
        $fees = $feeCalculator->calculateFundraisingFees($amount, $methodCode);

        $intent = FundraisingPaymentIntent::createFundraisingPaymentIntent([
            'fundraising_id' => $fundraising->id,
            'user_id' => $request->user()?->id,
            'amount' => $amount,
            'fees' => $fees,
            'currency' => strtoupper($data['currency'] ?? $fundraising->currency),
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'meta' => $data['meta'] ?? [],
            'expires_at' => now()->addMinutes(20),
        ]);

        return response()->json([
            'data' => [
                'key' => $intent->key,
                'status' => $intent->status,
                'amount' => (float) $intent->amount,
                'fees' => (float) $intent->fees,
                'currency' => $intent->currency,
                'expires_at' => $intent->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function checkout(string $key, Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_code' => ['required', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $intent = FundraisingPaymentIntent::query()->where('key', $key)->firstOrFail();
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
        $intent = FundraisingPaymentIntent::query()->where('key', $key)->firstOrFail();
        $paid = $intent->verify();

        return response()->json([
            'data' => [
                'key' => $intent->key,
                'status' => $intent->fresh()->status,
                'paid' => $paid,
            ],
        ]);
    }

    public function message(string $key, Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:191'],
            'is_amount_visible' => ['nullable', 'boolean'],
        ]);

        $intent = FundraisingPaymentIntent::query()->where('key', $key)->firstOrFail();
        $meta = (array) ($intent->meta ?? []);
        $intent->meta = array_merge($meta, $data);
        $intent->save();

        return response()->json(['data' => ['key' => $intent->key, 'meta' => $intent->meta]]);
    }

    public function email(string $key, Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_email' => ['required', 'email'],
        ]);

        $intent = FundraisingPaymentIntent::query()->where('key', $key)->firstOrFail();
        $intent->customer_email = $data['customer_email'];
        $intent->save();

        return response()->json(['data' => ['key' => $intent->key, 'customer_email' => $intent->customer_email]]);
    }
}

