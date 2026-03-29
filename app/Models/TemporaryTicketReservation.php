<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryTicketReservation extends Model
{
    protected $fillable = [
        'order_intent_id',
        'ticket_type_id',
        'quantity',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function orderIntent(): BelongsTo
    {
        return $this->belongsTo(OrderIntent::class, 'order_intent_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }
}
