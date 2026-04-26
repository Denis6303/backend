<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventEarning;
use App\Models\EventOccurrence;
use App\Models\TicketType;
use App\Services\Stats\EventStatsService;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin - Occurrences
 *
 * Administration des occurrences (résumé/earnings/ticket types).
 */
class OccurrencesAdminController extends Controller
{
    public function summary(int $id, EventStatsService $stats): JsonResponse
    {
        $occurrence = EventOccurrence::query()->with(['commission', 'serviceCosts'])->findOrFail($id);

        return response()->json([
            'data' => $stats->summary($occurrence),
        ]);
    }

    public function earnings(int $id): JsonResponse
    {
        $occurrence = EventOccurrence::query()->with(['commission', 'serviceCosts'])->findOrFail($id);
        $gross = $occurrence->calculateTotalRevenue();
        $discount = $occurrence->calculateTotalDiscount();
        $fees = $occurrence->calculateTotalFees();
        $commission = $occurrence->calculateCommissionTotal($gross);
        $recipe = $occurrence->calculateRecipe();

        $earning = EventEarning::query()->updateOrCreate(
            ['event_occurrence_id' => $occurrence->id],
            [
                'gross_revenue' => $gross,
                'discount_total' => $discount,
                'fees_total' => $fees,
                'commission_total' => $commission,
                'net_revenue' => $recipe,
                'calculated_at' => now(),
            ]
        );

        return response()->json([
            'data' => $earning,
        ]);
    }

    public function ticketTypes(int $id): JsonResponse
    {
        $occurrence = EventOccurrence::query()->findOrFail($id);

        $types = TicketType::query()
            ->where('event_occurrence_id', $occurrence->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $types,
        ]);
    }
}

