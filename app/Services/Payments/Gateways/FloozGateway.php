<?php

namespace App\Services\Payments\Gateways;

use App\Models\OrderIntent;
use Illuminate\Support\Str;

/**
 * Flooz — service Moov Money / Flooz (Togo).
 * Implémentation provisoire jusqu’au branchement de l’agrégateur / API officielle.
 */
class FloozGateway extends FakeGateway
{
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array
    {
        return [
            'external_reference' => 'flooz_' . Str::random(20),
            'meta' => [
                'provider' => 'flooz',
                'order_intent_key' => $intent->key,
                'payload' => $payload,
            ],
        ];
    }
}
