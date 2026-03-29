<?php

namespace App\Services\Payments;

use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\Gateways\FloozGateway;
use App\Services\Payments\Gateways\YassGateway;
use InvalidArgumentException;

/**
 * Registre des prestataires de paiement (checkout + verify sur OrderIntent).
 * Moyens supportés : Yass (Mixx by Yass) et Flooz (Moov). Code `fake` réservé au fallback interne.
 */
class PaymentGatewayRegistry
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    private array $map = [
        'fake' => FakeGateway::class,
        'yass' => YassGateway::class,
        'flooz' => FloozGateway::class,
    ];

    public function for(string $providerCode): PaymentGateway
    {
        $code = strtolower(trim($providerCode));
        $class = $this->map[$code] ?? null;

        if (! $class) {
            $class = FakeGateway::class;
        }

        $gateway = app($class);
        if (! $gateway instanceof PaymentGateway) {
            throw new InvalidArgumentException("Invalid gateway for provider [$providerCode].");
        }

        return $gateway;
    }
}
