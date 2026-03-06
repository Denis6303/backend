<?php

namespace App\Services\Finance;

use App\Models\EmailWalletReservation;
use App\Models\Ticket;
use App\Models\UserWalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function refundTicket(Ticket $ticket, float $amount, ?string $reason = null): ?UserWalletTransaction
    {
        $currency = $ticket->order?->currency ?? ($ticket->occurrence?->item?->currency ?? 'XOF');

        return $this->creditUserOrEmail(
            $ticket->user,
            $ticket->email,
            $currency,
            $amount,
            'refund',
            $reason ?? 'ticket_refund',
            [
                'ticket_id' => $ticket->id,
                'order_id' => $ticket->order_id,
            ]
        );
    }

    private function creditUserOrEmail($user, ?string $email, string $currency, float $amount, string $type, string $reason, array $meta = []): ?UserWalletTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $email, $currency, $amount, $type, $reason, $meta) {
            $reservationId = null;

            if (! $user && $email) {
                $reservation = EmailWalletReservation::create([
                    'email' => $email,
                    'currency' => $currency,
                    'amount' => $amount,
                    'reason' => $reason,
                    'meta' => $meta,
                ]);
                $reservationId = $reservation->id;
            }

            return UserWalletTransaction::create([
                'user_id' => $user?->id,
                'email_wallet_reservation_id' => $reservationId,
                'currency' => $currency,
                'amount' => $amount,
                'type' => $type,
                'reason' => $reason,
                'meta' => $meta,
            ]);
        });
    }
}

