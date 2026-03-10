<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOccurrenceServiceCost extends Model
{
    /**
     * Table des coûts de service par occurrence d'événement.
     */
    protected $table = 'event_occurrence_service_costs';

    protected $fillable = [
        'event_occurrence_id',
        'label',
        'amount',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }
}

