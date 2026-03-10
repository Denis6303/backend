<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEarning extends Model
{
    /**
     * Table des revenus d'occurrence d'événement.
     */
    protected $table = 'event_earnings';

    protected $fillable = [
        'event_occurrence_id',
        'gross_revenue',
        'discount_total',
        'fees_total',
        'commission_total',
        'net_revenue',
        'calculated_at',
    ];

    protected $casts = [
        'gross_revenue' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'fees_total' => 'decimal:2',
        'commission_total' => 'decimal:2',
        'net_revenue' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }
}

