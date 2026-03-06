<?php

namespace App\Services\Payments\Gateways;

use App\Models\OrderIntent;
use Illuminate\Support\Str;

class DjamoGateway extends FakeGateway
{
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array
    {
        return [
            'djamo_charge_id' => 'djamo_' . Str::random(20),
            'meta' => [
                'provider' => 'djamo',
                'payload' => $payload,
            ],
        ];
    }
}

