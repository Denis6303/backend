<?php

namespace App\Services\Search;

use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EventSearch
{
    public function __construct(private readonly SearchService $searchService)
    {
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function query(array $filters = []): Builder
    {
        $q = Event::query()->with(['occurrences.ticketTypes', 'category']);

        if (! empty($filters['statuses']) && is_array($filters['statuses'])) {
            $q->whereIn('status', $filters['statuses']);
        } elseif (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (array_key_exists('is_private', $filters)) {
            $q->where('is_private', (bool) $filters['is_private']);
        }

        if (! empty($filters['country_code'])) {
            $q->where('country_code', $filters['country_code']);
        }

        if (! empty($filters['city'])) {
            $q->where('city', $filters['city']);
        }

        if (! empty($filters['category_id'])) {
            $q->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['require_future_occurrence'])) {
            // Référence temps : UTC (GMT+0), aligné sur les datetimes stockées côté API.
            $now = Carbon::now('UTC');
            $q->whereHas('occurrences', function (Builder $occ) use ($now) {
                $occ->where('status', '!=', EventOccurrence::STATUS_CANCELLED)
                    ->whereNull('cancelled_at')
                    ->where(function (Builder $w) use ($now) {
                        $w->where(function (Builder $x) use ($now) {
                            $x->whereNotNull('end_date')->where('end_date', '>=', $now);
                        })->orWhere(function (Builder $x) use ($now) {
                            $x->whereNull('end_date')->where('start_date', '>=', $now);
                        });
                    });
            });
        }

        $this->searchService->applyTerm($q, $filters['q'] ?? null, ['title', 'description', 'city', 'address', 'slug']);

        return $q->orderByDesc('order_priority')->orderByDesc('id');
    }
}

