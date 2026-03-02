<?php

namespace App\Services\Payments;

use App\Services\Payments\Gateways\CardGateway;
use App\Services\Payments\Gateways\DjamoGateway;
use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\Gateways\Hub2Gateway;
use App\Services\Payments\Gateways\InTouchGateway;
use App\Services\Payments\Gateways\MobileMoneyGateway;
use App\Services\Payments\Gateways\PaystackGateway;
use App\Services\Payments\Gateways\WaveGateway;
use InvalidArgumentException;

class PaymentGatewayRegistry
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    private array $map = [
        'fake' => FakeGateway::class,
        'wave' => WaveGateway::class,
        'djamo' => DjamoGateway::class,
        'paystack' => PaystackGateway::class,
        'hub2' => Hub2Gateway::class,
        'intouch' => InTouchGateway::class,
        'mobile_money' => MobileMoneyGateway::class,
        'card' => CardGateway::class,
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

