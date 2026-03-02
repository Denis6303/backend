<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundraisingCommission extends Model
{
    protected $fillable = [
        'fundraising_id',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'priority',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function fundraising(): BelongsTo
    {
        return $this->belongsTo(Fundraising::class);
    }
}

