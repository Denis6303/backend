<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemEarning;
use App\Models\ItemOccurrence;
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
        $occurrence = ItemOccurrence::query()->with(['commission', 'serviceCosts'])->findOrFail($id);

        return response()->json([
            'data' => $stats->summary($occurrence),
        ]);
    }

    public function earnings(int $id): JsonResponse
    {
        $occurrence = ItemOccurrence::query()->with(['commission', 'serviceCosts'])->findOrFail($id);

        $earning = $occurrence->itemEarning;
        if (! $earning) {
            $gross = $occurrence->calculateTotalRevenue();
            $discount = $occurrence->calculateTotalDiscount();
            $fees = (float) $occurrence->orders()->where('status', 'confirmed')->sum('fees');
            $recipe = $occurrence->calculateRecipe();

            $earning = ItemEarning::create([
                'item_occurrence_id' => $occurrence->id,
                'gross_revenue' => $gross,
                'discount_total' => $discount,
                'fees_total' => $fees,
                'commission_total' => max(0, $gross - $fees - $recipe),
                'net_revenue' => $recipe,
                'calculated_at' => now(),
            ]);
        }

        return response()->json([
            'data' => $earning,
        ]);
    }

    public function ticketTypes(int $id): JsonResponse
    {
        $occurrence = ItemOccurrence::query()->findOrFail($id);

        $types = TicketType::query()
            ->where('item_occurrence_id', $occurrence->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $types,
        ]);
    }
}

