<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRefund extends Model
{
    protected $fillable = [
        'ticket_id',
        'order_id',
        'user_wallet_transaction_id',
        'currency',
        'amount',
        'rate',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'rate' => 'decimal:3',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(UserWalletTransaction::class, 'user_wallet_transaction_id');
    }
}

