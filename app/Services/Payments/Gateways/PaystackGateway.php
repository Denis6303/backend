<?php

namespace App\Services\Payments\Gateways;

use App\Models\OrderIntent;
use Illuminate\Support\Str;

class PaystackGateway extends FakeGateway
{
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array
    {
        return [
            'paystack_reference' => 'paystack_' . Str::random(24),
            'meta' => [
                'provider' => 'paystack',
                'payload' => $payload,
            ],
        ];
    }
}

