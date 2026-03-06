<?php

namespace App\Services\Payments\Gateways;

use App\Models\OrderIntent;
use Illuminate\Support\Str;

class WaveGateway extends FakeGateway
{
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array
    {
        return [
            'wave_checkout_id' => 'wave_' . Str::random(20),
            'meta' => [
                'provider' => 'wave',
                'payload' => $payload,
            ],
        ];
    }
}

