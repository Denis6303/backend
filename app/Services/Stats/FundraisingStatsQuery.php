<?php

namespace App\Services\Stats;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FundraisingStatsQuery
{
    public function forFundraising(int $fundraisingId): Builder
    {
        return DB::table('fundraising_contributions')
            ->selectRaw('count(*) as contributions_count')
            ->selectRaw('coalesce(sum(amount),0) as total_amount')
            ->selectRaw('coalesce(sum(fees),0) as total_fees')
            ->where('fundraising_id', $fundraisingId)
            ->whereNotNull('paid_at');
    }
}

