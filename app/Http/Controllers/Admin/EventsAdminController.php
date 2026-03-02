<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemOccurrenceCommission;
use App\Models\ItemOccurrenceServiceCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventsAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = Item::query()
            ->with(['occurrences'])
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 25));

        return response()->json($events);
    }

    public function verify(int $id): JsonResponse
    {
        $event = Item::query()->findOrFail($id);
        $event->is_verified = true;
        $event->save();

        return response()->json(['data' => $event->toArrayApi()]);
    }

    public function publish(int $id): JsonResponse
    {
        $event = Item::query()->findOrFail($id);
        $event->publish();

        return response()->json(['data' => $event->toArrayApi()]);
    }

    public function unpublish(int $id): JsonResponse
    {
        $event = Item::query()->findOrFail($id);
        $event->unpublish();

        return response()->json(['data' => $event->toArrayApi()]);
    }

    public function commission(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'occurrence_id' => ['nullable', 'integer', 'exists:item_occurrences,id'],
            'commission_percentage' => ['nullable', 'numeric', 'min:0'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $event = Item::query()->findOrFail($id);
        $event->commission_percentage = $data['commission_percentage'] ?? $event->commission_percentage;
        $event->commission_amount = $data['commission_amount'] ?? $event->commission_amount;
        $event->save();

        if (! empty($data['occurrence_id'])) {
            ItemOccurrenceCommission::updateOrCreate(
                ['item_occurrence_id' => (int) $data['occurrence_id']],
                [
                    'commission_percentage' => $data['commission_percentage'] ?? null,
                    'commission_amount' => $data['commission_amount'] ?? null,
                ]
            );
        }

        return response()->json(['data' => $event->fresh()->toArrayApi()]);
    }

    public function serviceCosts(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'occurrence_id' => ['required', 'integer', 'exists:item_occurrences,id'],
            'costs' => ['required', 'array'],
            'costs.*.label' => ['required', 'string', 'max:191'],
            'costs.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $event = Item::query()->findOrFail($id);

        DB::transaction(function () use ($data) {
            ItemOccurrenceServiceCost::query()
                ->where('item_occurrence_id', (int) $data['occurrence_id'])
                ->delete();

            foreach ($data['costs'] as $c) {
                ItemOccurrenceServiceCost::create([
                    'item_occurrence_id' => (int) $data['occurrence_id'],
                    'label' => $c['label'],
                    'amount' => $c['amount'],
                ]);
            }
        });

        return response()->json(['data' => $event->fresh()->toArrayApi()]);
    }

    public function assignAdminOwner(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $event = Item::query()->findOrFail($id);
        $oldOwner = $event->user_id;
        $event->user_id = (int) $data['user_id'];
        $event->save();

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'old_owner_user_id' => $oldOwner,
                'new_owner_user_id' => $event->user_id,
            ],
        ]);
    }

    public function restoreOwner(int $id): JsonResponse
    {
        // Placeholder: owner history table not defined in the spec list.
        $event = Item::query()->findOrFail($id);

        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'restored' => false,
                'message' => 'Owner history is not persisted in this Laravel 10 scaffold.',
            ],
        ], 422);
    }

    public function ownerHistory(int $id): JsonResponse
    {
        // Placeholder: owner history table not defined in the spec list.
        Item::query()->findOrFail($id);

        return response()->json([
            'data' => [],
        ]);
    }
}

