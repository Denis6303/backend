<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOccurrenceCommission extends Model
{
    /**
     * Table des commissions par occurrence d'événement.
     */
    protected $table = 'event_occurrence_commissions';

    protected $fillable = [
        'event_occurrence_id',
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
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }
}

