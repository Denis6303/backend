<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    protected $fillable = [
        'item_occurrence_id',
        'name',
        'description',
        'general_conditions',
        'price',
        'last_price',
        'total_quantity',
        'remaining_quantity',
        'real_remaining_quantity',
        'printed_quantity',
        'tag',
        'tag_id',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'last_price' => 'decimal:2',
    ];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ItemOccurrence::class, 'item_occurrence_id');
    }

    public function tagModel(): BelongsTo
    {
        return $this->belongsTo(TicketTag::class, 'tag_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(TicketTypePromotion::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function toArrayApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'status' => $this->status,
            'remaining_quantity' => $this->remaining_quantity,
            'tag' => $this->tag,
        ];
    }
}

