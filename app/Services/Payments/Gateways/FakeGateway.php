<?php

namespace App\Services\Payments\Gateways;

use App\Models\OrderIntent;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeGateway implements PaymentGateway
{
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array
    {
        return [
            'external_reference' => 'fake_' . Str::random(16),
            'meta' => [
                'provider' => 'fake',
                'order_intent_key' => $intent->key,
                'payload' => $payload,
            ],
        ];
    }

    public function verifyOrderIntentPayment(OrderIntent $intent): bool
    {
        if (config('payments.simulate_successful_payment_verify', false)) {
            Log::warning('PAYMENTS_SIMULATE_SUCCESSFUL_VERIFY is enabled: payment treated as paid.', [
                'order_intent_id' => $intent->id,
                'order_intent_key' => $intent->key,
            ]);

            return true;
        }

        // In local/dev, default to "paid" unless explicitly set paid=false.
        $providerMeta = (array) ($intent->paymentProvider?->meta ?? []);
        $flag = $providerMeta['paid'] ?? ($intent->meta['paid'] ?? null);

        return $flag === null ? (app()->environment('local', 'development', 'testing')) : (bool) $flag;
    }
}

