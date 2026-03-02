<?php

namespace App\Services\Payments\Gateways;

use App\Models\FundraisingPaymentIntent;
use App\Models\OrderIntent;
use Illuminate\Support\Str;

class Hub2Gateway extends FakeGateway
{
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array
    {
        return [
            'hub2_reference' => 'hub2_' . Str::random(20),
            'meta' => [
                'provider' => 'hub2',
                'payload' => $payload,
            ],
        ];
    }

    public function createCheckoutForFundraisingIntent(FundraisingPaymentIntent $intent, array $payload = []): array
    {
        return [
            'hub2_reference' => 'hub2_' . Str::random(20),
            'meta' => [
                'provider' => 'hub2',
                'payload' => $payload,
            ],
        ];
    }
}

