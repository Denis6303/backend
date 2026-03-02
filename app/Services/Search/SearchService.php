<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;

class SearchService
{
    /**
     * @param Builder $query
     * @param string|null $term
     * @param array<int,string> $columns
     */
    public function applyTerm(Builder $query, ?string $term, array $columns): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term, $columns) {
            foreach ($columns as $col) {
                $q->orWhere($col, 'like', '%' . $term . '%');
            }
        });
    }
}

