<?php

namespace App\Models;

use App\Jobs\HandleEventOccurrenceCancellation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventOccurrence extends Model
{
    /**
     * Table des occurrences d'événements.
     */
    protected $table = 'event_occurrences';

    protected $fillable = [
        'event_id',
        'subtitle',
        'start_date',
        'end_date',
        'status',
        'free_event',
        'nb_visites',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'free_event' => 'bool',
        'cancelled_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class, 'event_occurrence_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'event_occurrence_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'event_occurrence_id');
    }

    public function orderIntents(): HasMany
    {
        return $this->hasMany(OrderIntent::class, 'event_occurrence_id');
    }

    public function discountCodes(): HasMany
    {
        return $this->hasManyThrough(DiscountCode::class, Discount::class, 'event_occurrence_id', 'discount_id');
    }

    public function validator(): HasMany
    {
        return $this->hasMany(Validator::class, 'event_occurrence_id');
    }

    public function eventEarning(): HasOne
    {
        return $this->hasOne(EventEarning::class, 'event_occurrence_id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(EventOccurrenceCommission::class, 'event_occurrence_id');
    }

    public function serviceCosts(): HasMany
    {
        return $this->hasMany(EventOccurrenceServiceCost::class, 'event_occurrence_id');
    }

    public function cancel(?string $reason = null): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $this->status = 'cancelled';
        $this->cancelled_at = now();
        $this->save();

        HandleEventOccurrenceCancellation::dispatch($this->id, $reason);
    }

    public function markAsComplete(): void
    {
        if ($this->status === 'completed') {
            return;
        }

        $this->status = 'completed';
        $this->save();

        $this->tickets()
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $this->ticketTypes()
            ->each(function (TicketType $type) {
                $type->promotions()->where('status', 'active')->update(['status' => 'stopped']);
            });

        $this->discountCodes()
            ->where('status', 'active')
            ->update(['status' => 'expired']);
    }

    public function calculateTotalRevenue(): float
    {
        return (float) $this->orders()
            ->selectRaw('COALESCE(SUM(total_price), 0) as total')
            ->value('total');
    }
}

