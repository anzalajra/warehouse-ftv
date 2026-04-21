<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Rental;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use UnitEnum;
use Livewire\WithPagination;
use Illuminate\Contracts\Pagination\Paginator;

class Schedule extends Page implements HasActions
{
    use InteractsWithActions;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|UnitEnum|null $navigationGroup = 'Rentals';
    protected static ?string $navigationLabel = 'Schedule';
    protected static ?string $title = 'Schedule';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.schedule';

    /** @var string 'order' | 'product' */
    public string $filter = 'order';

    /** @var string 'month' | 'week' | 'day' */
    public string $view_mode = 'month';

    /** ISO date (Y-m-d) anchoring the current view. */
    public string $anchor;

    public ?string $search = '';
    public int $perPage = 15;

    protected $queryString = [
        'filter' => ['except' => 'order'],
        'view_mode' => ['except' => 'month'],
        'anchor' => ['except' => ''],
        'search' => ['except' => ''],
        'perPage' => ['except' => 15],
    ];

    public function mount(): void
    {
        if (empty($this->anchor)) {
            $this->anchor = now()->toDateString();
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['order', 'product']) ? $filter : 'order';
    }

    public function setViewMode(string $mode): void
    {
        $this->view_mode = in_array($mode, ['month', 'week', 'day']) ? $mode : 'month';
    }

    public function goToday(): void
    {
        $this->anchor = now()->toDateString();
    }

    public function navigate(int $direction): void
    {
        $anchor = Carbon::parse($this->anchor);
        $unit = match ($this->view_mode) {
            'day' => 'day',
            'week' => 'week',
            default => 'month',
        };
        $this->anchor = ($direction >= 0 ? $anchor->add($unit, 1) : $anchor->sub($unit, 1))->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Current visible date range based on filter + view_mode.
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function getVisibleRange(): array
    {
        $anchor = Carbon::parse($this->anchor);

        if ($this->filter === 'product') {
            $start = $anchor->copy()->startOfMonth();
            $end = $anchor->copy()->addMonths(2)->endOfMonth();
            $label = $start->format('M Y') . ' – ' . $end->format('M Y');
            return compact('start', 'end', 'label');
        }

        return match ($this->view_mode) {
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

    /**
     * Fetch rentals overlapping a given date range.
     */
    protected function fetchRentalsIn(Carbon $start, Carbon $end)
    {
        return Rental::query()
            ->select(['id', 'user_id', 'rental_code', 'status', 'start_date', 'end_date', 'total', 'notes'])
            ->with([
                'customer:id,name',
                'items:id,rental_id,product_unit_id',
                'items.productUnit:id,serial_number,product_id',
                'items.productUnit.product:id,name',
            ])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->orderBy('start_date')
            ->limit(500)
            ->get();
    }

    /**
     * Data for Month view: 6-week grid of days.
     */
    public function getMonthData(): array
    {
        $anchor = Carbon::parse($this->anchor);
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

        // Build segments per week
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
            // Sort by start col desc by length (longest first)
            usort($segs, function ($a, $b) {
                if ($a['start_col'] !== $b['start_col']) return $a['start_col'] <=> $b['start_col'];
                return ($b['end_col'] - $b['start_col']) <=> ($a['end_col'] - $a['start_col']);
            });

            // Assign lanes
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

    /**
     * Data for Week view (gantt).
     */
    public function getWeekData(): array
    {
        $anchor = Carbon::parse($this->anchor);
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

    /**
     * Data for Day view (timeline).
     */
    public function getDayData(): array
    {
        $anchor = Carbon::parse($this->anchor);
        $start = $anchor->copy()->startOfDay();
        $end = $anchor->copy()->endOfDay();

        $rentals = $this->fetchRentalsIn($start, $end);

        $events = [];
        $allDay = [];
        foreach ($rentals as $r) {
            $rs = $r->start_date;
            $re = $r->end_date;
            // If rental spans more than one day and covers the anchor, treat as all-day
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

        // Week strip around the anchor
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

    public function viewRentalDetailsAction(): Action
    {
        return Action::make('viewRentalDetails')
            ->modalHeading('Rental Details')
            ->modalWidth('2xl')
            ->form(fn (array $arguments) => [
                Grid::make(2)
                    ->schema([
                        TextInput::make('rental_code')
                            ->label('Rental Code')
                            ->disabled(),
                        TextInput::make('status')
                            ->label('Status')
                            ->disabled(),
                        TextInput::make('customer_name')
                            ->label('Customer')
                            ->disabled(),
                        TextInput::make('total')
                            ->label('Total Amount')
                            ->disabled(),
                        TextInput::make('start_date')
                            ->label('Start Date')
                            ->disabled(),
                        TextInput::make('end_date')
                            ->label('End Date')
                            ->disabled(),
                        Textarea::make('items')
                            ->label('Rented Units')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
            ])
            ->fillForm(function (array $arguments) {
                $rental = Rental::with(['customer', 'items.productUnit.product'])->find($arguments['rentalId']);
                if (!$rental) return [];

                $items = $rental->items->map(function ($item) {
                    $pu = $item->productUnit;
                    return ($pu?->product?->name ?? '-') . ' (' . ($pu->serial_number ?? '-') . ')';
                })->join("\n");

                return [
                    'rental_code' => $rental->rental_code,
                    'status' => ucfirst($rental->status),
                    'customer_name' => $rental->customer->name,
                    'total' => 'Rp ' . number_format($rental->total, 0, ',', '.'),
                    'start_date' => $rental->start_date->format('d M Y H:i'),
                    'end_date' => $rental->end_date->format('d M Y H:i'),
                    'items' => $items,
                    'notes' => $rental->notes,
                ];
            })
            ->modalFooterActions(fn (array $arguments) => [
                Action::make('viewRentalPage')
                    ->label('View Rental')
                    ->color('primary')
                    ->url(fn () => "/admin/rentals/{$arguments['rentalId']}/view"),
            ]);
    }

    public function getProductsWithUnitsAndRentals(): Paginator
    {
        $range = $this->getVisibleRange();
        $rangeStart = $range['start'];
        $rangeEnd = $range['end'];

        $query = Product::with(['units.rentalItems.rental.customer'])
            ->whereHas('units');

        $search = trim($this->search ?? '');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhereHas('units', function ($q) use ($search) {
                      $q->where('serial_number', 'like', '%' . $search . '%');
                  });
            });
        }

        $products = $query->paginate($this->perPage);

        $products->getCollection()->transform(function ($product) use ($rangeStart, $rangeEnd) {
            $productData = [
                'product' => $product,
                'units' => [],
            ];

            foreach ($product->units as $unit) {
                $rentals = [];
                foreach ($unit->rentalItems as $item) {
                    $rental = $item->rental;
                    if (!$rental) continue;
                    if ($rental->end_date >= $rangeStart && $rental->start_date <= $rangeEnd) {
                        $rentals[] = [
                            'id' => $rental->id,
                            'code' => $rental->rental_code,
                            'customer' => $rental->customer?->name ?? '—',
                            'start' => $rental->start_date,
                            'end' => $rental->end_date,
                            'status' => $rental->status,
                            'color' => Rental::getStatusColor($rental->status),
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

    /**
     * Build the day headers for the by-product view.
     * Returns an array of arrays with: date (Carbon), is_today, is_weekend, month_key, month_label.
     */
    public function getProductDayHeaders(): array
    {
        $range = $this->getVisibleRange();
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

    /**
     * Group consecutive day headers by month for merged header row.
     */
    public function getProductMonthGroups(array $headers): array
    {
        $groups = [];
        foreach ($headers as $i => $h) {
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
