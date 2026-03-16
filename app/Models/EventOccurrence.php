<?php

namespace App\Models;

use App\Jobs\HandleEventOccurrenceCancellation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventOccurrence extends Model
{
    public const STATUS_SAVED = Event::STATUS_SAVED;
    public const STATUS_UPCOMING = Event::STATUS_UPCOMING;
    public const STATUS_COMPLETED = Event::STATUS_COMPLETED;
    public const STATUS_CANCELLED = Event::STATUS_CANCELLED;

    /**
     * Table des occurrences d'événements.
     */
    protected $table = 'event_occurrences';

    protected $fillable = [
        'event_id',
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
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->save();

        HandleEventOccurrenceCancellation::dispatch($this->id, $reason);
    }

    public function markAsComplete(): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }

        $this->status = self::STATUS_COMPLETED;
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

    public function toArrayApi(): array
    {
        $this->loadMissing('ticketTypes');

        return [
            'id' => $this->id,
            'start_date' => optional($this->start_date)?->toIso8601String(),
            'end_date' => optional($this->end_date)?->toIso8601String(),
            'status' => $this->status,
            'free_event' => (bool) $this->free_event,
            'ticket_types' => $this->ticketTypes->map(function (TicketType $t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'description' => $t->description,
                    'general_conditions' => $t->general_conditions,
                    'price' => $t->price,
                    'last_price' => $t->last_price,
                    'total_quantity' => $t->total_quantity,
                    'remaining_quantity' => $t->remaining_quantity,
                    'real_remaining_quantity' => $t->real_remaining_quantity,
                    'printed_quantity' => $t->printed_quantity,
                    'status' => $t->status,
                ];
            })->values()->all(),
        ];
    }
}

