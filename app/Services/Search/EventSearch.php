<?php

namespace App\Services\Search;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;

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

        $this->searchService->applyTerm($q, $filters['q'] ?? null, ['title', 'description', 'city', 'address']);

        return $q->orderByDesc('order_priority')->orderByDesc('id');
    }
}

