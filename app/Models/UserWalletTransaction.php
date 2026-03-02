<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'email_wallet_reservation_id',
        'currency',
        'amount',
        'type',
        'reason',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailWalletReservation(): BelongsTo
    {
        return $this->belongsTo(EmailWalletReservation::class);
    }
}

