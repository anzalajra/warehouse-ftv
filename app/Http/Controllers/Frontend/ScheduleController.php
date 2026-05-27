<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Rental;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleController extends Controller
{
    /**
     * Color/label map for rental statuses. Hex + label are derived from the
     * Rental model so all calendar surfaces (storefront + admin widgets) stay in sync.
     */
    protected function buildStatuses(): array
    {
        $keys = [
            Rental::STATUS_QUOTATION,
            Rental::STATUS_CONFIRMED,
            Rental::STATUS_ACTIVE,
            Rental::STATUS_COMPLETED,
            Rental::STATUS_CANCELLED,
            Rental::STATUS_LATE_PICKUP,
            Rental::STATUS_LATE_RETURN,
            Rental::STATUS_PARTIAL_RETURN,
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = [Rental::getStatusHexColor($k), Rental::getStatusLabel($k)];
        }
        return $out;
    }

    protected function buildLegend(): array
    {
        // Dedup by hex+label so late_pickup/late_return collapse into one row.
        $seen = [];
        $legend = [];
        foreach ($this->buildStatuses() as $row) {
            $key = $row[0] . '|' . $row[1];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $legend[] = $row;
        }
        return $legend;
    }

    protected array $statuses = [];
    protected array $legend = [];

    public function __construct()
    {
        $this->statuses = $this->buildStatuses();
        $this->legend = $this->buildLegend();
    }

    public function index(Request $request)
    {
        $filter = in_array($request->query('filter'), ['order', 'product']) ? $request->query('filter') : 'order';
        $viewMode = in_array($request->query('view_mode'), ['month', 'week', 'day']) ? $request->query('view_mode') : 'month';
        $anchor = $request->query('anchor');
        $anchor = $anchor ? Carbon::parse($anchor)->toDateString() : now()->toDateString();
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('perPage', 15);
        if (! in_array($perPage, [15, 35, 55, 75, 95])) {
            $perPage = 15;
        }

        $range = $this->getVisibleRange($anchor, $filter, $viewMode);

        $payload = [
            'filter' => $filter,
            'view_mode' => $viewMode,
            'anchor' => $anchor,
            'search' => $search,
            'perPage' => $perPage,
            'range' => $range,
            'statuses' => $this->statuses,
            'legend' => $this->legend,
        ];

        if ($filter === 'order') {
            if ($viewMode === 'month') {
                $payload['monthData'] = $this->getMonthData($anchor);
            } elseif ($viewMode === 'week') {
                $payload['weekData'] = $this->getWeekData($anchor);
            } else {
                $payload['dayData'] = $this->getDayData($anchor);
            }
        } else {
            $payload['products'] = $this->getProductsWithUnitsAndRentals($range, $search, $perPage);
            $payload['dayHeaders'] = $this->getProductDayHeaders($range);
            $payload['monthGroups'] = $this->getProductMonthGroups($payload['dayHeaders']);
        }

        return view('frontend.schedule.index', $payload);
    }

    public function rentalDetails(Rental $rental)
    {
        $rental->load(['customer:id,name', 'items.productUnit.product:id,name']);

        $items = $rental->items->map(function ($item) {
            $pu = $item->productUnit;
            return ($pu?->product?->name ?? '-');
        })->filter()->values()->unique()->join(', ');

        return response()->json([
            'customer' => $rental->customer?->name ?? '—',
            'status' => ucfirst(str_replace('_', ' ', $rental->status)),
            'status_color' => $this->statuses[$rental->status][0] ?? '#6b7280',
            'start' => $rental->start_date?->format('d M Y H:i'),
            'end' => $rental->end_date?->format('d M Y H:i'),
            'items' => $items,
        ]);
    }

    protected function getVisibleRange(string $anchorDate, string $filter, string $viewMode): array
    {
        $anchor = Carbon::parse($anchorDate);

        if ($filter === 'product') {
            $start = $anchor->copy()->startOfMonth();
            $end = $anchor->copy()->addMonths(2)->endOfMonth();
            $label = $start->format('M Y') . ' – ' . $end->format('M Y');
            return compact('start', 'end', 'label');
        }

        return match ($viewMode) {
            'day' => [
                'start' => $anchor->copy()->startOfDay(),
                'end' => $anchor->copy()->endOfDay(),
                'label' => $anchor->format('F j, Y'),
            ],
            'week' => [
                'start' => $anchor->copy()->startOfWeek(Carbon::MONDAY),
                'end' => $anchor->copy()->endOfWeek(Carbon::SUNDAY),
                'label' => $anchor->copy()->startOfWeek(Carbon::MONDAY)->format('M j')
                    . ' – ' . $anchor->copy()->endOfWeek(Carbon::SUNDAY)->format('M j, Y'),
            ],
            default => [
                'start' => $anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                'end' => $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
                'label' => $anchor->format('F Y'),
            ],
        };
    }

    protected function fetchRentalsIn(Carbon $start, Carbon $end)
    {
        return Rental::query()
            ->select(['id', 'user_id', 'status', 'start_date', 'end_date'])
            ->with([
                'customer:id,name',
            ])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->orderBy('start_date')
            ->limit(500)
            ->get();
    }

    protected function getMonthData(string $anchorDate): array
    {
        $anchor = Carbon::parse($anchorDate);
        $monthStart = $anchor->copy()->startOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        $cur = $gridStart->copy();
        while ($cur <= $gridEnd) {
            $days[] = $cur->copy();
            $cur->addDay();
        }

        $weeks = array_chunk($days, 7);
        $rentals = $this->fetchRentalsIn($gridStart, $gridEnd);

        $weekSegments = [];
        foreach ($weeks as $wIdx => $week) {
            $weekStart = $week[0]->copy()->startOfDay();
            $weekEnd = $week[6]->copy()->endOfDay();

            $segs = [];
            foreach ($rentals as $r) {
                $rs = $r->start_date->copy()->startOfDay();
                $re = $r->end_date->copy()->startOfDay();
                if ($re < $weekStart || $rs > $weekEnd) continue;

                $segStart = $rs->greaterThan($weekStart) ? $rs : $weekStart;
                $segEnd = $re->lessThan($weekEnd) ? $re : $weekEnd->copy()->startOfDay();

                $segs[] = [
                    'rental' => $r,
                    'start_col' => (int) $weekStart->diffInDays($segStart),
                    'end_col' => (int) $weekStart->diffInDays($segEnd),
                ];
            }

            usort($segs, function ($a, $b) {
                if ($a['start_col'] !== $b['start_col']) return $a['start_col'] <=> $b['start_col'];
                return ($b['end_col'] - $b['start_col']) <=> ($a['end_col'] - $a['start_col']);
            });

            $lanes = [];
            foreach ($segs as &$seg) {
                $placed = false;
                foreach ($lanes as $laneIdx => $laneItems) {
                    $last = end($laneItems);
                    if ($last['end_col'] < $seg['start_col']) {
                        $lanes[$laneIdx][] = $seg;
                        $seg['lane'] = $laneIdx;
                        $placed = true;
                        break;
                    }
                }
                if (! $placed) {
                    $lanes[] = [$seg];
                    $seg['lane'] = count($lanes) - 1;
                }
            }
            unset($seg);
            $weekSegments[$wIdx] = $segs;
        }

        return [
            'weeks' => $weeks,
            'weekSegments' => $weekSegments,
            'monthStart' => $monthStart,
        ];
    }

    protected function getWeekData(string $anchorDate): array
    {
        $anchor = Carbon::parse($anchorDate);
        $start = $anchor->copy()->startOfWeek(Carbon::MONDAY);
        $end = $anchor->copy()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $start->copy()->addDays($i);
        }

        $rentals = $this->fetchRentalsIn($start, $end);

        $rows = [];
        foreach ($rentals as $r) {
            $rs = $r->start_date->copy()->startOfDay();
            $re = $r->end_date->copy()->startOfDay();
            $startCol = max(0, (int) $start->diffInDays($rs));
            $endCol = min(6, (int) $start->diffInDays($re));
            $rows[] = [
                'rental' => $r,
                'start_col' => $startCol,
                'end_col' => $endCol,
            ];
        }

        return [
            'days' => $days,
            'rows' => $rows,
        ];
    }

    protected function getDayData(string $anchorDate): array
    {
        $anchor = Carbon::parse($anchorDate);
        $start = $anchor->copy()->startOfDay();
        $end = $anchor->copy()->endOfDay();

        $rentals = $this->fetchRentalsIn($start, $end);

        $events = [];
        $allDay = [];
        foreach ($rentals as $r) {
            $rs = $r->start_date;
            $re = $r->end_date;
            $isAllDay = $rs->lessThan($start) || $re->greaterThan($end);

            $startH = $rs->greaterThanOrEqualTo($start) ? $rs->hour + $rs->minute / 60 : 7;
            $endH = $re->lessThanOrEqualTo($end) ? $re->hour + $re->minute / 60 : 21;
            if ($endH <= $startH) $endH = min(21, $startH + 1);

            $row = [
                'rental' => $r,
                'start_h' => $startH,
                'end_h' => $endH,
                'all_day' => $isAllDay,
            ];

            if ($isAllDay) {
                $allDay[] = $row;
            } else {
                $events[] = $row;
            }
        }

        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY);
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $weekDays[] = $weekStart->copy()->addDays($i);
        }

        return [
            'events' => $events,
            'allDay' => $allDay,
            'weekDays' => $weekDays,
            'anchor' => $anchor,
        ];
    }

    public function dayRentals(Request $request)
    {
        $date = $request->query('date');
        if (! $date) {
            return response()->json([]);
        }
        $d = Carbon::parse($date)->startOfDay();
        $rentals = Rental::query()
            ->with(['customer:id,name'])
            ->where('start_date', '<=', $d->copy()->endOfDay())
            ->where('end_date', '>=', $d)
            ->orderBy('start_date')
            ->get();

        return response()->json($rentals->map(fn ($r) => [
            'id' => $r->id,
            'customer' => $r->customer?->name ?? '—',
            'status' => ucfirst(str_replace('_', ' ', $r->status)),
            'status_color' => $this->statuses[$r->status][0] ?? '#6b7280',
            'start' => $r->start_date?->format('j M H:i'),
            'end' => $r->end_date?->format('j M H:i'),
        ])->values());
    }

    protected function getProductsWithUnitsAndRentals(array $range, string $search, int $perPage): Paginator
    {
        $rangeStart = $range['start'];
        $rangeEnd = $range['end'];

        $query = Product::with(['units.rentalItems.rental.customer'])
            ->whereHas('units');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('units', function ($q) use ($search) {
                        $q->where('serial_number', 'like', '%' . $search . '%');
                    });
            });
        }

        $products = $query->paginate($perPage)->withQueryString();

        $products->getCollection()->transform(function ($product) use ($rangeStart, $rangeEnd) {
            $productData = [
                'product' => $product,
                'units' => [],
            ];

            foreach ($product->units as $unit) {
                $rentals = [];
                foreach ($unit->rentalItems as $item) {
                    $rental = $item->rental;
                    if (! $rental) continue;
                    if ($rental->end_date >= $rangeStart && $rental->start_date <= $rangeEnd) {
                        $rentals[] = [
                            'id' => $rental->id,
                            'customer' => $rental->customer?->name ?? '—',
                            'start' => $rental->start_date,
                            'end' => $rental->end_date,
                            'status' => $rental->status,
                        ];
                    }
                }
                $productData['units'][] = [
                    'unit' => $unit,
                    'rentals' => $rentals,
                ];
            }
            return $productData;
        });

        return $products;
    }

    protected function getProductDayHeaders(array $range): array
    {
        $start = $range['start']->copy();
        $end = $range['end']->copy();

        $headers = [];
        $cur = $start->copy();
        while ($cur <= $end) {
            $headers[] = [
                'date' => $cur->copy(),
                'is_today' => $cur->isToday(),
                'is_weekend' => $cur->isWeekend(),
                'month_key' => $cur->format('Y-m'),
                'month_label' => $cur->format('F Y'),
            ];
            $cur->addDay();
        }
        return $headers;
    }

    protected function getProductMonthGroups(array $headers): array
    {
        $groups = [];
        foreach ($headers as $h) {
            $last = end($groups);
            if (! $last || $last['month_key'] !== $h['month_key']) {
                $groups[] = [
                    'month_key' => $h['month_key'],
                    'label' => $h['month_label'],
                    'count' => 1,
                ];
            } else {
                $groups[count($groups) - 1]['count']++;
            }
        }
        return $groups;
    }
}
