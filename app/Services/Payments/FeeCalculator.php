<?php

namespace App\Services\Payments;

class FeeCalculator
{
    public function calculateEventFees(float $amount, string $paymentMethodCode = 'api'): float
    {
        $cfg = config('fees.events', []);
        $percent = (float) ($cfg['percent'] ?? 0);
        $fixed = (float) ($cfg['fixed'] ?? 0);

        $methodOverrides = (array) ($cfg['by_method'][$paymentMethodCode] ?? []);
        if (array_key_exists('percent', $methodOverrides)) {
            $percent = (float) $methodOverrides['percent'];
        }
        if (array_key_exists('fixed', $methodOverrides)) {
            $fixed = (float) $methodOverrides['fixed'];
        }

        return round(($amount * $percent / 100) + $fixed, 2);
    }

    public function calculateFundraisingFees(float $amount, string $paymentMethodCode = 'api'): float
    {
        $cfg = config('fees.fundraisings', []);
        $percent = (float) ($cfg['percent'] ?? 0);
        $fixed = (float) ($cfg['fixed'] ?? 0);

        $methodOverrides = (array) ($cfg['by_method'][$paymentMethodCode] ?? []);
        if (array_key_exists('percent', $methodOverrides)) {
            $percent = (float) $methodOverrides['percent'];
        }
        if (array_key_exists('fixed', $methodOverrides)) {
            $fixed = (float) $methodOverrides['fixed'];
        }

        return round(($amount * $percent / 100) + $fixed, 2);
    }
}

