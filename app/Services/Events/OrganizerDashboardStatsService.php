<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Order;
use App\Models\Ticket;
use Carbon\Carbon;

class OrganizerDashboardStatsService
{
    /**
     * @param  array<int>  $eventIdsFilter  Empty = all events owned by the user
     * @return array<string, mixed>
     */
    public function build(
        int $userId,
        Carbon $start,
        Carbon $end,
        array $eventIdsFilter,
        string $granularity,
        string $chartMetric,
        string $locale
    ): array {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $durationDays = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($durationDays - 1)->startOfDay();

        $eventsQuery = Event::query()->where('user_id', $userId);
        if ($eventIdsFilter !== []) {
            $eventsQuery->whereIn('id', $eventIdsFilter);
        }
        $eventIds = $eventsQuery->pluck('id')->all();

        if ($eventIds === []) {
            return $this->emptyPayload($start, $end, $prevStart, $prevEnd, $granularity, $chartMetric, $locale);
        }

        $occurrenceIds = EventOccurrence::query()->whereIn('event_id', $eventIds)->pluck('id')->all();

        $currency = $this->resolveCurrency($eventIds);

        $current = $this->aggregatePeriod($occurrenceIds, $eventIds, $start, $end);
        $previous = $this->aggregatePeriod($occurrenceIds, $eventIds, $prevStart, $prevEnd);

        $chart = $this->buildChart(
            $occurrenceIds,
            $eventIds,
            $start,
            $end,
            $granularity,
            $chartMetric,
            $locale,
            (int) $current['page_views']
        );

        return [
            'period' => [
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'previous_start' => $prevStart->toIso8601String(),
                'previous_end' => $prevEnd->toIso8601String(),
            ],
            'currency' => $currency,
            'events_selected_count' => count($eventIds),
            'summary' => [
                'revenue' => $this->summaryMetric((float) $current['revenue'], (float) $previous['revenue'], true),
                'orders' => $this->summaryMetric((int) $current['orders'], (int) $previous['orders'], false),
                'page_views' => $this->summaryMetricPageViews((int) $current['page_views']),
                'ticket_sales' => $this->summaryMetric((int) $current['tickets'], (int) $previous['tickets'], false),
            ],
            'chart' => $chart,
        ];
    }

    /**
     * @param  array<int>  $eventIds
     * @return array{revenue: float|int, orders: int, tickets: int, page_views: int}
     */
    private function aggregatePeriod(array $occurrenceIds, array $eventIds, Carbon $start, Carbon $end): array
    {
        $revenue = (float) Order::query()
            ->whereIn('event_occurrence_id', $occurrenceIds)
            ->where('status', 'active')
            ->whereBetween('created_at', [$start, $end])
            // "Total vendu" côté organisateur = montant ticket net (hors frais plateforme).
            // amount inclut déjà les frais dans ce projet: net sold = amount - fees.
            ->selectRaw('COALESCE(SUM(amount - COALESCE(fees, 0)), 0) as net')
            ->value('net');

        $orders = (int) Order::query()
            ->whereIn('event_occurrence_id', $occurrenceIds)
            ->where('status', 'active')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $tickets = (int) Ticket::query()
            ->where('status', '!=', 'cancelled')
            ->whereHas('order', function ($q) use ($occurrenceIds, $start, $end): void {
                $q->whereIn('event_occurrence_id', $occurrenceIds)
                    ->where('status', 'active')
                    ->whereBetween('created_at', [$start, $end]);
            })
            ->count();

        $pageViews = (int) Event::query()->whereIn('id', $eventIds)->sum('nb_visites');

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'tickets' => $tickets,
            'page_views' => $pageViews,
        ];
    }

    /**
     * @param  array<int>  $occurrenceIds
     * @param  array<int>  $eventIds
     * @return array<string, mixed>
     */
    private function buildChart(
        array $occurrenceIds,
        array $eventIds,
        Carbon $start,
        Carbon $end,
        string $granularity,
        string $chartMetric,
        string $locale,
        int $totalPageViewsCumulative
    ): array {
        Carbon::setLocale($locale);

        $keys = $this->bucketKeys($start, $end, $granularity);
        $labels = array_map(fn (string $key) => $this->labelForBucket($key, $granularity, $locale), $keys);

        $values = match ($chartMetric) {
            'revenue' => $this->seriesRevenue($occurrenceIds, $start, $end, $granularity, $keys),
            'orders' => $this->seriesOrders($occurrenceIds, $start, $end, $granularity, $keys),
            'ticket_sales' => $this->seriesTicketSales($occurrenceIds, $start, $end, $granularity, $keys),
            default => $this->seriesPageViewsApprox($keys, $totalPageViewsCumulative),
        };

        return [
            'granularity' => $granularity,
            'metric' => $chartMetric,
            'labels' => $labels,
            'values' => array_values($values),
            'page_views_is_approximation' => $chartMetric === 'page_views',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function bucketKeys(Carbon $start, Carbon $end, string $granularity): array
    {
        $startDay = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        $keys = [];

        if ($granularity === 'daily') {
            for ($d = $startDay->copy(); $d->lte($endDay); $d->addDay()) {
                $keys[] = $d->format('Y-m-d');
            }

            return $keys;
        }

        if ($granularity === 'monthly') {
            $d = $startDay->copy()->startOfMonth();
            while ($d->lte($endDay)) {
                $keys[] = $d->format('Y-m');
                $d->addMonth();
            }

            return $keys;
        }

        // weekly: one point per ISO week (Monday key)
        $d = $startDay->copy()->startOfWeek(Carbon::MONDAY);
        $last = $endDay->copy()->startOfWeek(Carbon::MONDAY);
        while ($d->lte($last)) {
            $keys[] = $d->format('Y-m-d');
            $d->addWeek();
        }

        return $keys;
    }

    private function labelForBucket(string $key, string $granularity, string $locale): string
    {
        Carbon::setLocale($locale);

        if ($granularity === 'daily') {
            return Carbon::parse($key)->translatedFormat('j M');
        }

        if ($granularity === 'monthly') {
            return Carbon::createFromFormat('Y-m', $key)->translatedFormat('M Y');
        }

        $monday = Carbon::parse($key)->startOfWeek(Carbon::MONDAY);
        $sunday = $monday->copy()->endOfWeek(Carbon::SUNDAY);

        return $monday->translatedFormat('j M').' – '.$sunday->translatedFormat('j M');
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, float>
     */
    private function seriesRevenue(array $occurrenceIds, Carbon $start, Carbon $end, string $granularity, array $keys): array
    {
        $base = array_fill_keys($keys, 0.0);
        $rows = Order::query()
            ->whereIn('event_occurrence_id', $occurrenceIds)
            ->where('status', 'active')
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at', 'amount', 'fees']);

        foreach ($rows as $row) {
            $k = $this->bucketKeyForDate(Carbon::parse($row->created_at), $granularity);
            if (! array_key_exists($k, $base)) {
                continue;
            }
            $base[$k] += max(0.0, (float) $row->amount - (float) ($row->fees ?? 0));
        }

        return $base;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, int>
     */
    private function seriesOrders(array $occurrenceIds, Carbon $start, Carbon $end, string $granularity, array $keys): array
    {
        $base = array_fill_keys($keys, 0);
        $rows = Order::query()
            ->whereIn('event_occurrence_id', $occurrenceIds)
            ->where('status', 'active')
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        foreach ($rows as $row) {
            $k = $this->bucketKeyForDate(Carbon::parse($row->created_at), $granularity);
            if (! array_key_exists($k, $base)) {
                continue;
            }
            $base[$k]++;
        }

        return $base;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, int>
     */
    private function seriesTicketSales(array $occurrenceIds, Carbon $start, Carbon $end, string $granularity, array $keys): array
    {
        $base = array_fill_keys($keys, 0);
        $rows = Ticket::query()
            ->where('status', '!=', 'cancelled')
            ->whereHas('order', function ($q) use ($occurrenceIds, $start, $end): void {
                $q->whereIn('event_occurrence_id', $occurrenceIds)
                    ->where('status', 'active')
                    ->whereBetween('created_at', [$start, $end]);
            })
            ->with(['order' => fn ($q) => $q->select('id', 'created_at')])
            ->get(['order_id']);

        foreach ($rows as $row) {
            $created = $row->order?->created_at;
            if (! $created) {
                continue;
            }
            $k = $this->bucketKeyForDate(Carbon::parse($created), $granularity);
            if (! array_key_exists($k, $base)) {
                continue;
            }
            $base[$k]++;
        }

        return $base;
    }

    /**
     * No per-day visit history: split cumulative total evenly across buckets for the chart.
     *
     * @param  array<int, string>  $keys
     * @return array<string, float>
     */
    private function seriesPageViewsApprox(array $keys, int $total): array
    {
        $n = count($keys);
        if ($n === 0) {
            return [];
        }
        $base = array_fill_keys($keys, 0.0);
        $each = intdiv($total, $n);
        $rem = $total % $n;
        $i = 0;
        foreach ($keys as $key) {
            $base[$key] = (float) ($each + ($i < $rem ? 1 : 0));
            $i++;
        }

        return $base;
    }

    private function bucketKeyForDate(Carbon $dt, string $granularity): string
    {
        return match ($granularity) {
            'daily' => $dt->copy()->format('Y-m-d'),
            'monthly' => $dt->copy()->format('Y-m'),
            default => $dt->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
        };
    }

    /**
     * @param  array<int>  $eventIds
     */
    private function resolveCurrency(array $eventIds): string
    {
        if ($eventIds === []) {
            return 'XOF';
        }

        $currencies = Event::query()->whereIn('id', $eventIds)->pluck('currency')->filter()->unique()->values()->all();

        return count($currencies) === 1 ? (string) $currencies[0] : 'XOF';
    }

    /**
     * @return array{value: float|int, previous_value: float|int, change_percent: float}
     */
    private function summaryMetric(float|int $current, float|int $previous, bool $isFloat): array
    {
        if ($isFloat) {
            $current = round((float) $current, 2);
            $previous = round((float) $previous, 2);
        }

        $change = 0.0;
        if ($previous > 0) {
            $change = round((($current - $previous) / $previous) * 100, 2);
        } elseif ($current > 0) {
            $change = 100.0;
        }

        return [
            'value' => $current,
            'previous_value' => $previous,
            'change_percent' => $change,
        ];
    }

    /**
     * @return array{value: int, previous_value: int, change_percent: float, note: string}
     */
    private function summaryMetricPageViews(int $total): array
    {
        return [
            'value' => $total,
            'previous_value' => $total,
            'change_percent' => 0.0,
            'note' => 'cumulative_counter',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(
        Carbon $start,
        Carbon $end,
        Carbon $prevStart,
        Carbon $prevEnd,
        string $granularity,
        string $chartMetric,
        string $locale
    ): array {
        $keys = $this->bucketKeys($start, $end, $granularity);
        $labels = array_map(fn (string $key) => $this->labelForBucket($key, $granularity, $locale), $keys);
        $zeros = array_fill(0, count($keys), $chartMetric === 'revenue' ? 0.0 : 0);

        return [
            'period' => [
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'previous_start' => $prevStart->toIso8601String(),
                'previous_end' => $prevEnd->toIso8601String(),
            ],
            'currency' => 'XOF',
            'events_selected_count' => 0,
            'summary' => [
                'revenue' => $this->summaryMetric(0.0, 0.0, true),
                'orders' => $this->summaryMetric(0, 0, false),
                'page_views' => $this->summaryMetricPageViews(0),
                'ticket_sales' => $this->summaryMetric(0, 0, false),
            ],
            'chart' => [
                'granularity' => $granularity,
                'metric' => $chartMetric,
                'labels' => $labels,
                'values' => $zeros,
                'page_views_is_approximation' => $chartMetric === 'page_views',
            ],
        ];
    }
}
