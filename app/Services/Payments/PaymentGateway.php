<?php

namespace App\Services\Payments;

use App\Models\FundraisingPaymentIntent;
use App\Models\OrderIntent;

interface PaymentGateway
{
    /**
     * @return array<string,mixed> Provider reference fields to persist on PaymentProvider
     */
    public function createCheckoutForOrderIntent(OrderIntent $intent, array $payload = []): array;

    public function verifyOrderIntentPayment(OrderIntent $intent): bool;

    /**
     * @return array<string,mixed> Provider reference fields to persist on PaymentProvider
     */
    public function createCheckoutForFundraisingIntent(FundraisingPaymentIntent $intent, array $payload = []): array;

    public function verifyFundraisingPayment(FundraisingPaymentIntent $intent): bool;
}

