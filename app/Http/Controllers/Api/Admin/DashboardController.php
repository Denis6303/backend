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
        $salesTotal = $this->computeSalesSnapshot();
        $salesLast30 = $this->computeSalesSnapshot(now()->subDays(30), null);
        $salesPrev30 = $this->computeSalesSnapshot(now()->subDays(60), now()->subDays(30));

        $revenueTotal = $salesTotal['net_sold'];
        $revenueLast30 = $salesLast30['net_sold'];
        $revenuePrev30 = $salesPrev30['net_sold'];
        $platformCommissionTotal = $salesTotal['platform_commission'];
        $platformCommissionLast30 = $salesLast30['platform_commission'];
        $platformCommissionPrev30 = $salesPrev30['platform_commission'];
        $ticketsSold = $salesTotal['tickets_sold'];
        $ticketsLast30 = $salesLast30['tickets_sold'];
        $ticketsPrev30 = $salesPrev30['tickets_sold'];

        // Total événements en base (hors annulés) — ce que l’on attend souvent sous « nombre d’événements ».
        $totalEvents = Event::query()
            ->where('status', '!=', Event::STATUS_CANCELLED)
            ->count();
        // Événements actuellement à la vente (publiés « upcoming »).
        $eventsOnSale = Event::query()->where('status', Event::STATUS_UPCOMING)->count();
        $eventsOnSaleWeek = Event::query()
            ->where('status', Event::STATUS_UPCOMING)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Acheteurs distincts (emails de commandes payées) — plus représentatif que le seul compte `users`.
        $buyersTotal = $this->distinctBuyerCount();
        $buyersLast30 = $this->distinctBuyerCount(now()->subDays(30), null);
        $buyersPrev30 = $this->distinctBuyerCount(now()->subDays(60), now()->subDays(30));

        $accountsTotal = User::query()->where('is_admin', false)->count();

        return [
            'currency' => 'FCFA',
            'total_revenue' => [
                'value' => round($revenueTotal, 2),
                'growth_percent' => $this->growthPercent($revenueLast30, $revenuePrev30),
            ],
            'platform_commission' => [
                'value' => round($platformCommissionTotal, 2),
                'growth_percent' => $this->growthPercent($platformCommissionLast30, $platformCommissionPrev30),
            ],
            'tickets_sold' => [
                'value' => $ticketsSold,
                'growth_percent' => $this->growthPercent($ticketsLast30, $ticketsPrev30),
            ],
            'active_events' => [
                'value' => $totalEvents,
                'on_sale' => $eventsOnSale,
                'new_this_week' => $eventsOnSaleWeek,
            ],
            'total_customers' => [
                'value' => $buyersTotal,
                'growth_percent' => $this->growthPercent($buyersLast30, $buyersPrev30),
                'registered_accounts' => $accountsTotal,
            ],
        ];
    }

    /**
     * Calcule les ventes depuis les billets (prix net client) pour éviter d'inclure
     * les frais techniques dans les montants affichés.
     *
     * - Commission par occurrence/event:
     *   1) override occurrence (event_occurrence_commissions)
     *   2) fallback event
     *   3) défaut plateforme = 10%
     *
     * @return array{gross_sold: float, platform_commission: float, net_sold: float, tickets_sold: int}
     */
    private function computeSalesSnapshot(?Carbon $from = null, ?Carbon $until = null): array
    {
        $ticketsQuery = Ticket::query()
            ->selectRaw('event_occurrence_id, COUNT(*) as tickets_count, COALESCE(SUM(price), 0) as gross')
            ->where('status', '!=', 'cancelled');

        if ($from !== null) {
            $ticketsQuery->where('created_at', '>=', $from);
        }
        if ($until !== null) {
            $ticketsQuery->where('created_at', '<', $until);
        }

        $rows = $ticketsQuery
            ->groupBy('event_occurrence_id')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'gross_sold' => 0.0,
                'platform_commission' => 0.0,
                'net_sold' => 0.0,
                'tickets_sold' => 0,
            ];
        }

        $occurrenceIds = $rows
            ->pluck('event_occurrence_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $occurrences = EventOccurrence::query()
            ->with(['event:id,commission_percentage,commission_amount', 'commission'])
            ->whereIn('id', $occurrenceIds)
            ->get()
            ->keyBy('id');

        $grossTotal = 0.0;
        $commissionTotal = 0.0;
        $ticketsTotal = 0;

        foreach ($rows as $row) {
            $occurrenceId = (int) ($row->event_occurrence_id ?? 0);
            $gross = round((float) ($row->gross ?? 0), 2);
            $tickets = (int) ($row->tickets_count ?? 0);

            $occurrence = $occurrences->get($occurrenceId);
            $commissionPct = $occurrence?->commission?->commission_percentage;
            $commissionFixed = $occurrence?->commission?->commission_amount;

            if ($commissionPct === null) {
                $commissionPct = $occurrence?->event?->commission_percentage;
            }
            if ($commissionFixed === null) {
                $commissionFixed = $occurrence?->event?->commission_amount;
            }

            $pct = (float) ($commissionPct ?? 10.0);
            $fixed = (float) ($commissionFixed ?? 0.0);
            $commission = max(0.0, round(($gross * $pct / 100) + $fixed, 2));

            $grossTotal += $gross;
            $commissionTotal += $commission;
            $ticketsTotal += $tickets;
        }

        $netSold = max(0.0, round($grossTotal - $commissionTotal, 2));

        return [
            'gross_sold' => round($grossTotal, 2),
            'platform_commission' => round($commissionTotal, 2),
            'net_sold' => $netSold,
            'tickets_sold' => $ticketsTotal,
        ];
    }

    /**
     * Nombre d’emails distincts ayant au moins une commande « active » (montant encaissé côté modèle Order).
     */
    private function distinctBuyerCount(?Carbon $from = null, ?Carbon $until = null): int
    {
        $q = Order::query()
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($from !== null) {
            $q->where('created_at', '>=', $from);
        }
        if ($until !== null) {
            $q->where('created_at', '<', $until);
        }

        return (int) $q->selectRaw('COUNT(DISTINCT LOWER(TRIM(email))) as c')->value('c');
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
            'currency' => 'FCFA',
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
                    // Afficher "total vendu" net (hors frais plateforme).
                    'amount' => max(0.0, (float) $order->amount - (float) ($order->fees ?? 0)),
                    'platform_fees' => (float) ($order->fees ?? 0),
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
                    'currency' => 'FCFA',
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

