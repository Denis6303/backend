<?php

namespace App\Services\Stats;

use App\Models\Fundraising;

class FundraisingStatsService
{
    public function __construct(private readonly FundraisingStatsQuery $query)
    {
    }

    public function summary(Fundraising $fundraising): array
    {
        $row = (array) $this->query->forFundraising($fundraising->id)->first();

        return [
            'fundraising_id' => $fundraising->id,
            'contributions_count' => (int) ($row['contributions_count'] ?? 0),
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'total_fees' => (float) ($row['total_fees'] ?? 0),
            'current_amount' => (float) $fundraising->current_amount,
            'is_goal_reached' => $fundraising->getIsGoalReached(),
        ];
    }
}

