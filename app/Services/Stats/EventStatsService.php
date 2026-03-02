<?php

namespace App\Services\Stats;

use App\Models\ItemOccurrence;
use Illuminate\Support\Facades\DB;

class EventStatsService
{
    public function __construct(private readonly EventStatsQuery $query)
    {
    }

    public function summary(ItemOccurrence $occurrence): array
    {
        $row = (array) $this->query->forOccurrence($occurrence->id)->first();

        $ticketsCount = (int) DB::table('tickets')
            ->where('item_occurrence_id', $occurrence->id)
            ->count();

        return [
            'occurrence_id' => $occurrence->id,
            'orders_count' => (int) ($row['orders_count'] ?? 0),
            'tickets_count' => $ticketsCount,
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'total_fees' => (float) ($row['total_fees'] ?? 0),
            'total_discounts' => (float) ($row['total_discounts'] ?? 0),
            'recipe' => $occurrence->calculateRecipe(),
        ];
    }
}

