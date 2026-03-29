<?php

namespace App\Services\Payments\Gateways;

use App\Models\OrderIntent;
use Illuminate\Support\Str;

/**
 * Yass — réseau mobile money Mixx by Yass (Togo).
 * Implémentation provisoire jusqu’au branchement de l’agrégateur / API officielle.
 */
class YassGateway extends FakeGateway
{
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array
    {
        return [
            'external_reference' => 'yass_' . Str::random(20),
            'meta' => [
                'provider' => 'yass',
                'order_intent_key' => $intent->key,
                'payload' => $payload,
            ],
        ];
    }
}
