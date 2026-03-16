<?php

namespace App\Http\Controllers\Api\V1\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Search\EventSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Events
 *
 * Public and organizer endpoints for listing and viewing events.
 */
class EventController extends Controller
{
    /**
     * List public events.
     *
     * Returns a paginated list of public events with filters for search,
     * categories, country, status and sorting.
     *
     * @queryParam query string Search term applied to title, description, city and address. Example: concert
     * @queryParam location string Filter by city or address. Example: Lomé
     * @queryParam country_code string Optional country code (tg or other). Example: tg
     * @queryParam statuses[] string[] Optional list of statuses to filter events (saved, upcoming, completed, cancelled). Example: upcoming
     * @queryParam per_page integer Items per page (1-100). Example: 15
     *
     * @response 200 scenario="Success" {
     *   "data": [
     *     {
     *       "id": 1,
     *       "slug": "my-first-event",
     *       "title": "My first event",
     *       "status": "upcoming",
     *       "country_code": "tg",
     *       "city": "Lomé"
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 15,
     *     "total": 1
     *   }
     * }
     */
    public function index(Request $request, EventSearch $search): JsonResponse
    {
        $filters = [];

        // Map new query params to existing search filters
        $filters['q'] = $request->input('query', $request->input('q'));
        $filters['country_code'] = $request->input('country_code');
        $filters['city'] = $request->input('location', $request->input('city'));

        $statuses = (array) $request->input('statuses', []);
        if (! empty($statuses)) {
            $filters['statuses'] = $statuses;
        } elseif ($request->filled('status')) {
            $filters['status'] = $request->input('status');
        }

        if ($request->has('is_private')) {
            $filters['is_private'] = $request->boolean('is_private');
        }

        $events = $search->query($filters)->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'data' => $events->getCollection()->map(fn (Event $e) => $e->toArrayApi())->values(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Get public event details.
     *
     * Returns the public details of an event identified by its numeric ID,
     * including its occurrences and ticket types.
     *
     * @urlParam id integer required Event ID. Example: 1
     */
    public function show(string $version, $id): JsonResponse
    {
        $event = Event::query()
            ->with(['occurrences.ticketTypes', 'category'])
            ->findOrFail((int) $id);

        $event->increment('nb_visites');

        return response()->json([
            'data' => $event->toArrayApi(),
        ]);
    }

    /**
     * List current user's events.
     *
     * Returns a paginated list of events belonging to the authenticated
     * organizer, with optional filters on status and search term.
     *
     * @queryParam query string Search term applied to title, description, city and address. Example: concert
     * @queryParam statuses[] string[] Optional list of statuses (saved, upcoming, completed, cancelled). Example: upcoming
     * @queryParam per_page integer Items per page (1-100). Example: 15
     *
     * @authenticated
     */
    public function userIndex(Request $request, EventSearch $search): JsonResponse
    {
        $filters = [];
        $filters['q'] = $request->input('query', $request->input('q'));

        $statuses = (array) $request->input('statuses', []);
        if (! empty($statuses)) {
            $filters['statuses'] = $statuses;
        } elseif ($request->filled('status')) {
            $filters['status'] = $request->input('status');
        }

        $filters['user_id'] = $request->user()?->id;

        $query = $search->query($filters)->where('user_id', $filters['user_id']);
        $events = $query->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'data' => $events->getCollection()->map(fn (Event $e) => $e->toArrayApi())->values(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Get details of one of the current user's events.
     *
     * Returns full details of an event owned by the authenticated organizer.
     *
     * @urlParam id integer required Event ID. Example: 1
     *
     * @authenticated
     */
     public function userShow(Request $request, string $version, $id): JsonResponse
    {
        $event = Event::query()
            ->where('user_id', $request->user()?->id)
            ->with(['occurrences.ticketTypes', 'category'])
            ->findOrFail((int) $id);

        return response()->json([
            'data' => $event->toArrayApi(),
        ]);
    }
}

