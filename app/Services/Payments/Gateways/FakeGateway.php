<?php

namespace App\Services\Payments\Gateways;

use App\Models\FundraisingPaymentIntent;
use App\Models\OrderIntent;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Str;

class FakeGateway implements PaymentGateway
{
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array
    {
        return [
            'paystack_reference' => 'fake_' . Str::random(16),
            'meta' => [
                'provider' => 'fake',
                'order_intent_key' => $intent->key,
                'payload' => $payload,
            ],
        ];
    }

    public function verifyOrderIntentPayment(OrderIntent $intent): bool
    {
        // In local/dev, default to "paid" unless explicitly set paid=false.
        $providerMeta = (array) ($intent->paymentProvider?->meta ?? []);
        $flag = $providerMeta['paid'] ?? ($intent->meta['paid'] ?? null);

        return $flag === null ? (app()->environment('local', 'development', 'testing')) : (bool) $flag;
    }

    public function createCheckoutForFundraisingIntent(FundraisingPaymentIntent $intent, array $payload = []): array
    {
        return [
            'paystack_reference' => 'fake_' . Str::random(16),
            'meta' => [
                'provider' => 'fake',
                'fundraising_intent_key' => $intent->key,
                'payload' => $payload,
            ],
        ];
    }

    public function verifyFundraisingPayment(FundraisingPaymentIntent $intent): bool
    {
        $providerMeta = (array) ($intent->paymentProvider?->meta ?? []);
        $flag = $providerMeta['paid'] ?? ($intent->meta['paid'] ?? null);

        return $flag === null ? (app()->environment('local', 'development', 'testing')) : (bool) $flag;
    }
}

