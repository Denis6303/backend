<?php

namespace App\Services\Payments\Gateways;

use App\Models\FundraisingPaymentIntent;
use App\Models\OrderIntent;
use Illuminate\Support\Str;

class InTouchGateway extends FakeGateway
{
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array
    {
        return [
            'intouch_reference' => 'intouch_' . Str::random(20),
            'meta' => [
                'provider' => 'intouch',
                'payload' => $payload,
            ],
        ];
    }

    public function createCheckoutForFundraisingIntent(FundraisingPaymentIntent $intent, array $payload = []): array
    {
        return [
            'intouch_reference' => 'intouch_' . Str::random(20),
            'meta' => [
                'provider' => 'intouch',
                'payload' => $payload,
            ],
        ];
    }
}

