<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemOccurrenceCommission extends Model
{
    protected $fillable = [
        'item_occurrence_id',
        'commission_percentage',
        'commission_amount',
        'meta',
    ];

    protected $casts = [
        'commission_percentage' => 'decimal:3',
        'commission_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ItemOccurrence::class, 'item_occurrence_id');
    }
}

