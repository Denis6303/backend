<?php

namespace App\Http\Controllers\Api\V1\Event;

use App\Exceptions\ApiCodes;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Events\OrganizerDashboardStatsService;
use App\Services\Search\EventSearch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;

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
     * @queryParam category_id integer Optional category id (from GET /categories). Example: 2
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

        // Map new query params to existing search filters (`q` is an alias for `query`)
        $filters['q'] = $this->normalizedPublicSearchTerm($request);
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

        $categoryId = $request->input('category_id');
        if ($categoryId !== null && $categoryId !== '' && filter_var($categoryId, FILTER_VALIDATE_INT) !== false && (int) $categoryId > 0) {
            $filters['category_id'] = (int) $categoryId;
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
        $filters['q'] = $this->normalizedPublicSearchTerm($request);

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
     * Organizer dashboard statistics (summary + chart series).
     *
     * Returns revenue, orders, page views (cumulative counter on events), and ticket sales for the
     * selected period vs the immediately preceding period of the same length, plus time series for the chart.
     * Page views in the chart are an even split of the cumulative total across buckets (no per-day history).
     *
     * @queryParam start_date string required Period start (Y-m-d). Example: 2022-04-01
     * @queryParam end_date string required Period end (Y-m-d). Example: 2022-04-30
     * @queryParam event_ids integer[] Optional restrict to these event IDs (must belong to the user). No-example
     * @queryParam granularity string Chart buckets: daily, weekly, monthly. Example: weekly
     * @queryParam chart_metric string Series metric: revenue, orders, page_views, ticket_sales. Example: revenue
     * @queryParam locale string Optional locale for chart labels (fr, en). Example: fr
     *
     * @authenticated
     */
    public function organizerDashboardStats(Request $request, string $version): JsonResponse
    {
        $validated = $this->validateOrFail($request->all(), [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'event_ids' => ['sometimes', 'array'],
            'event_ids.*' => ['integer'],
            'granularity' => ['sometimes', 'string', 'in:daily,weekly,monthly'],
            'chart_metric' => ['sometimes', 'string', 'in:revenue,orders,page_views,ticket_sales'],
            'locale' => ['sometimes', 'string', 'in:fr,en'],
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();
        if ($start->diffInDays($end) + 1 > 366) {
            return ResponseBuilder::asError(ApiCodes::VALIDATION_EXCEPTION)
                ->withHttpCode(422)
                ->withMessage(__('messages.event.dashboard_stats_period_too_long'))
                ->build();
        }

        $granularity = $validated['granularity'] ?? 'weekly';
        $chartMetric = $validated['chart_metric'] ?? 'revenue';
        $locale = $validated['locale'] ?? $request->getPreferredLanguage(['fr', 'en']) ?? 'fr';
        $eventIds = array_map('intval', $validated['event_ids'] ?? []);

        $service = app(OrganizerDashboardStatsService::class);
        $data = $service->build(
            (int) $request->user()->id,
            $start,
            $end,
            $eventIds,
            $granularity,
            $chartMetric,
            $locale
        );

        return ResponseBuilder::asSuccess()
            ->withMessage(__('messages.event.dashboard_stats_retrieved'))
            ->withData($data)
            ->build();
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

    /**
     * Search term from `query` or legacy `q`: trim, empty → null, max 255 chars.
     */
    protected function normalizedPublicSearchTerm(Request $request): ?string
    {
        $raw = $request->input('query', $request->input('q'));
        if ($raw === null || ! is_string($raw)) {
            return null;
        }
        $t = trim($raw);
        if ($t === '') {
            return null;
        }

        return mb_strlen($t) > 255 ? mb_substr($t, 0, 255) : $t;
    }
}

