<?php

namespace App\Services\Search;

use App\Models\Fundraising;
use Illuminate\Database\Eloquent\Builder;

class FundraisingSearch
{
    public function __construct(private readonly SearchService $searchService)
    {
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function query(array $filters = []): Builder
    {
        $q = Fundraising::query()->with(['category']);

        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (array_key_exists('is_private', $filters)) {
            $q->where('is_private', (bool) $filters['is_private']);
        }

        if (! empty($filters['country_code'])) {
            $q->where('country_code', $filters['country_code']);
        }

        $this->searchService->applyTerm($q, $filters['q'] ?? null, ['title', 'description', 'beneficiary_display_name']);

        return $q->orderByDesc('order_priority')->orderByDesc('id');
    }
}

