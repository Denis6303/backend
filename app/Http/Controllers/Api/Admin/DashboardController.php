<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventEarning;
use App\Models\EventOccurrence;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'kpis' => $this->buildKpis(),
                'sales_overview' => $this->buildSalesOverview((int) $request->integer('months', 6)),
                'recent_activity' => $this->buildRecentActivity((int) $request->integer('limit', 8)),
                'upcoming_events' => $this->buildUpcomingEvents((int) $request->integer('limit', 6)),
                'recent_orders' => $this->buildRecentOrders((int) $request->integer('limit', 8)),
                'payouts' => $this->buildPayoutSummary((int) $request->integer('limit', 5)),
            ],
        ]);
    }

    public function kpis(): JsonResponse
    {
        return response()->json(['data' => $this->buildKpis()]);
    }

    public function salesOverview(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->buildSalesOverview((int) $request->integer('months', 6)),
        ]);
    }

    public function recentActivity(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->buildRecentActivity((int) $request->integer('limit', 8)),
        ]);
    }

    public function upcomingEvents(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->buildUpcomingEvents((int) $request->integer('limit', 6)),
        ]);
    }

    public function recentOrders(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->buildRecentOrders((int) $request->integer('limit', 8)),
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->buildPayoutSummary((int) $request->integer('limit', 5)),
        ]);
    }

    private function buildKpis(): array
    {
        $revenueTotal = (float) Order::query()
            ->where('status', 'active')
            ->sum('amount');

        $revenueLast30 = (float) Order::query()
            ->where('status', 'active')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('amount');

        $revenuePrev30 = (float) Order::query()
            ->where('status', 'active')
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->sum('amount');

        $ticketsSold = Ticket::query()->where('status', '!=', 'cancelled')->count();
        $ticketsLast30 = Ticket::query()
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $ticketsPrev30 = Ticket::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->count();

        $activeEvents = Event::query()->where('status', Event::STATUS_UPCOMING)->count();
        $activeEventsWeek = Event::query()
            ->where('status', Event::STATUS_UPCOMING)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $customersTotal = User::query()->where('is_admin', false)->count();
        $customersLast30 = User::query()
            ->where('is_admin', false)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $customersPrev30 = User::query()
            ->where('is_admin', false)
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->count();

        return [
            'total_revenue' => [
                'value' => round($revenueTotal, 2),
                'growth_percent' => $this->growthPercent($revenueLast30, $revenuePrev30),
            ],
            'tickets_sold' => [
                'value' => $ticketsSold,
                'growth_percent' => $this->growthPercent($ticketsLast30, $ticketsPrev30),
            ],
            'active_events' => [
                'value' => $activeEvents,
                'new_this_week' => $activeEventsWeek,
            ],
            'total_customers' => [
                'value' => $customersTotal,
                'growth_percent' => $this->growthPercent($customersLast30, $customersPrev30),
            ],
        ];
    }

    private function buildSalesOverview(int $months): array
    {
        $months = max(1, min(12, $months));
        $start = now()->startOfMonth()->subMonths($months - 1);
        $rows = Order::query()
            ->where('status', 'active')
            ->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(amount) as revenue, COUNT(*) as orders_count")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $bucket = (clone $start)->addMonths($i);
            $key = $bucket->format('Y-m');
            $current = $rows->get($key);
            $series[] = [
                'month' => $key,
                'label' => $bucket->translatedFormat('M Y'),
                'revenue' => round((float) ($current->revenue ?? 0), 2),
                'orders_count' => (int) ($current->orders_count ?? 0),
            ];
        }

        return [
            'currency' => 'XOF',
            'series' => $series,
        ];
    }

    private function buildRecentActivity(int $limit): array
    {
        $limit = max(1, min(20, $limit));

        $orderActivities = Order::query()
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'number', 'status', 'created_at'])
            ->map(fn (Order $o) => [
                'type' => 'order',
                'title' => 'Nouvelle commande reçue',
                'description' => 'Commande #'.($o->number ?: $o->id),
                'status' => $o->status,
                'occurred_at' => optional($o->created_at)?->toIso8601String(),
            ]);

        $eventActivities = Event::query()
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'title', 'status', 'updated_at'])
            ->map(fn (Event $e) => [
                'type' => 'event',
                'title' => 'Événement mis à jour',
                'description' => $e->title,
                'status' => $e->status,
                'occurred_at' => optional($e->updated_at)?->toIso8601String(),
            ]);

        $userActivities = User::query()
            ->where('is_admin', false)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'email', 'created_at'])
            ->map(function (User $u): array {
                $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));

                return [
                    'type' => 'customer',
                    'title' => 'Client enregistré',
                    'description' => $name !== '' ? $name : (string) $u->email,
                    'status' => 'registered',
                    'occurred_at' => optional($u->created_at)?->toIso8601String(),
                ];
            });

        return collect()
            ->concat($orderActivities)
            ->concat($eventActivities)
            ->concat($userActivities)
            ->sortByDesc(fn (array $i) => $i['occurred_at'])
            ->take($limit)
            ->values()
            ->all();
    }

    private function buildUpcomingEvents(int $limit): array
    {
        $limit = max(1, min(24, $limit));

        return EventOccurrence::query()
            ->with('event:id,title,status')
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->limit($limit)
            ->get()
            ->map(function (EventOccurrence $occurrence): array {
                $sold = (int) Ticket::query()
                    ->where('event_occurrence_id', $occurrence->id)
                    ->where('status', '!=', 'cancelled')
                    ->count();

                return [
                    'event_id' => $occurrence->event?->id,
                    'event_occurrence_id' => $occurrence->id,
                    'title' => $occurrence->event?->title,
                    'start_date' => optional($occurrence->start_date)?->toIso8601String(),
                    'status' => $occurrence->event?->status,
                    'tickets_sold' => $sold,
                ];
            })
            ->values()
            ->all();
    }

    private function buildRecentOrders(int $limit): array
    {
        $limit = max(1, min(20, $limit));

        return Order::query()
            ->with(['occurrence.event:id,title'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Order $order): array {
                $ticketCount = $order->tickets()->count();

                return [
                    'id' => $order->id,
                    'number' => $order->number,
                    'event_title' => $order->occurrence?->event?->title,
                    'customer_name' => $order->full_name,
                    'customer_email' => $order->email,
                    'tickets_count' => $ticketCount,
                    'amount' => (float) $order->amount,
                    'currency' => $order->currency,
                    'status' => $order->status,
                    'created_at' => optional($order->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildPayoutSummary(int $limit): array
    {
        $limit = max(1, min(20, $limit));
        $available = (float) EventEarning::query()->sum('net_revenue');
        $updatedAt = EventEarning::query()->max('updated_at');
        $totalPaid = (float) EventEarning::query()->sum('commission_total');

        $history = EventEarning::query()
            ->with('occurrence.event:id,title')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (EventEarning $row): array {
                return [
                    'event_occurrence_id' => $row->event_occurrence_id,
                    'event_title' => $row->occurrence?->event?->title,
                    'amount' => (float) $row->net_revenue,
                    'currency' => 'XOF',
                    'status' => 'calculated',
                    'reference' => 'EARNING-'.$row->id,
                    'created_at' => optional($row->updated_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return [
            'available_balance' => round($available, 2),
            'last_updated_at' => $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null,
            'totals' => [
                'total_paid' => round($totalPaid, 2),
                'transactions_count' => count($history),
            ],
            'history' => $history,
        ];
    }

    private function growthPercent(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 2);
    }
}

