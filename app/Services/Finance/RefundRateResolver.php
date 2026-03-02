<?php

namespace App\Services\Finance;

use App\Models\FundraisingContribution;
use App\Models\Ticket;

class RefundRateResolver
{
    public function resolveForTicket(Ticket $ticket): float
    {
        $cfg = config('refunds.ticket', []);

        if (($ticket->occurrence?->status ?? null) === 'cancelled') {
            return (float) ($cfg['when_event_cancelled'] ?? 1.0);
        }

        if ($ticket->validated_at) {
            return (float) ($cfg['when_validated'] ?? 0.0);
        }

        $start = $ticket->occurrence?->start_date;
        if (! $start) {
            return (float) ($cfg['default'] ?? 0.0);
        }

        $now = now();
        if ($now->gte($start)) {
            return (float) ($cfg['after_start'] ?? 0.0);
        }

        $hoursBefore = $now->diffInHours($start);
        foreach (($cfg['before_start_rules'] ?? []) as $rule) {
            $maxHours = (int) ($rule['max_hours_before_start'] ?? 0);
            $rate = (float) ($rule['rate'] ?? 0);

            if ($hoursBefore <= $maxHours) {
                return max(0.0, min(1.0, $rate));
            }
        }

        return (float) ($cfg['default'] ?? 1.0);
    }

    public function resolveForFundraisingContribution(FundraisingContribution $contribution): float
    {
        $cfg = config('refunds.fundraising', []);

        $fundraisingStatus = $contribution->fundraising?->status;
        if ($fundraisingStatus === 'stopped') {
            return (float) ($cfg['when_stopped'] ?? 1.0);
        }

        return (float) ($cfg['default'] ?? 0.0);
    }
}

