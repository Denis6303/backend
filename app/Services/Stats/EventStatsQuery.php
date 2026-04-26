<?php

namespace App\Services\Stats;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EventStatsQuery
{
    public function forOccurrence(int $occurrenceId): Builder
    {
        return DB::table('orders')
            ->selectRaw('count(*) as orders_count')
            ->selectRaw('coalesce(sum(amount),0) as total_amount')
            ->selectRaw('coalesce(sum(fees),0) as total_fees')
            ->selectRaw('coalesce(sum(discount_amount),0) as total_discounts')
            ->where('event_occurrence_id', $occurrenceId)
            ->where('status', 'active');
    }
}

