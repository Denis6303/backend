<?php

namespace App\Models;

use App\Jobs\HandleEventOccurrenceCancellation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class ItemOccurrence extends Model
{
    protected $fillable = [
        'item_id',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderIntents(): HasMany
    {
        return $this->hasMany(OrderIntent::class);
    }

    public function discountCodes(): HasMany
    {
        return $this->hasManyThrough(DiscountCode::class, Discount::class, 'item_occurrence_id', 'discount_id');
    }

    public function validator(): HasMany
    {
        return $this->hasMany(Validator::class);
    }

    public function itemEarning(): HasOne
    {
        return $this->hasOne(ItemEarning::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(ItemOccurrenceCommission::class);
    }

    public function serviceCosts(): HasMany
    {
        return $this->hasMany(ItemOccurrenceServiceCost::class);
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
            ->where('status', 'confirmed')
            ->sum(DB::raw('amount'));
    }

    public function calculateTotalDiscount(): float
    {
        return (float) $this->orders()
            ->where('status', 'confirmed')
            ->sum(DB::raw('discount_amount'));
    }

    public function calculateRecipe(): float
    {
        $gross = (float) $this->orders()->where('status', 'confirmed')->sum(DB::raw('amount'));
        $fees = (float) $this->orders()->where('status', 'confirmed')->sum(DB::raw('fees'));

        $commission = (float) ($this->commission?->commission_amount ?? 0);
        if ($commission <= 0 && ($this->commission?->commission_percentage ?? null) !== null) {
            $commission = $gross * ((float) $this->commission->commission_percentage / 100);
        }

        $serviceCosts = (float) $this->serviceCosts()->sum(DB::raw('amount'));

        return max(0, $gross - $fees - $commission - $serviceCosts);
    }

    public function toArrayApi(): array
    {
        $this->loadMissing(['ticketTypes']);

        return [
            'id' => $this->id,
            'subtitle' => $this->subtitle,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'status' => $this->status,
            'free_event' => $this->free_event,
            'ticket_types' => $this->ticketTypes->map(fn (TicketType $t) => $t->toArrayApi())->values()->all(),
        ];
    }
}

