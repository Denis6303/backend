<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailWalletReservation extends Model
{
    protected $fillable = [
        'email',
        'currency',
        'amount',
        'reason',
        'meta',
        'released_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'released_at' => 'datetime',
    ];

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(UserWalletTransaction::class);
    }
}

