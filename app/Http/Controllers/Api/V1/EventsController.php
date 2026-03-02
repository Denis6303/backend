<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\Search\EventSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    public function index(Request $request, EventSearch $search): JsonResponse
    {
        $filters = $request->only(['q', 'status', 'country_code', 'city', 'is_private']);

        $events = $search->query($filters)->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'data' => $events->getCollection()->map(fn (Item $i) => $i->toArrayApi())->values(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    public function show(string $idOrSlug): JsonResponse
    {
        $query = Item::query()->with(['occurrences.ticketTypes', 'category']);

        $event = is_numeric($idOrSlug)
            ? $query->findOrFail((int) $idOrSlug)
            : $query->where('slug', $idOrSlug)->firstOrFail();

        $event->increment('nb_visites');

        return response()->json([
            'data' => $event->toArrayApi(),
        ]);
    }
}

