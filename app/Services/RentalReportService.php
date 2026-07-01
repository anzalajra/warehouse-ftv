<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Invoice;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Operational RENTAL reporting aggregates for the unified Report page.
 *
 * Every public method accepts an optional [$start, $end] date window (Y-m-d strings);
 * when omitted it defaults to the current month — the same convention used by
 * {@see \App\Filament\Clusters\Finance\Pages\FinancialReports::mount()}.
 *
 * All grouping is done in PHP (not DATE()/strftime SQL) so the service behaves
 * identically on SQLite (local/tests) and MySQL (production). Monetary values are
 * read straight from stored columns — totals are NEVER recalculated here (that is
 * the job of RentalObserver::recalculateTotals()).
 */
class RentalReportService
{
    /** Statuses that represent a live/realized booking (excludes cancelled + expired). */
    public const REALIZED_STATUSES = [
        Rental::STATUS_CONFIRMED,
        Rental::STATUS_ACTIVE,
        Rental::STATUS_COMPLETED,
        Rental::STATUS_LATE_PICKUP,
        Rental::STATUS_LATE_RETURN,
        Rental::STATUS_PARTIAL_RETURN,
    ];

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function window(?string $start, ?string $end): array
    {
        $s = $start ? Carbon::parse($start)->startOfDay() : now()->startOfMonth();
        $e = $end ? Carbon::parse($end)->endOfDay() : now()->endOfMonth();

        return [$s, $e];
    }

    /**
     * Count + value of rentals created in the window, grouped by status, plus a
     * daily trend series of created counts.
     */
    public static function summary(?string $start = null, ?string $end = null): array
    {
        [$s, $e] = self::window($start, $end);

        $rentals = Rental::query()
            ->whereBetween('created_at', [$s, $e])
            ->get(['id', 'status', 'subtotal', 'total', 'created_at']);

        $byStatus = [];
        foreach (Rental::getStatusOptions() as $status => $label) {
            $rows = $rentals->where('status', $status);
            $byStatus[] = [
                'status' => $status,
                'label' => $label,
                'count' => $rows->count(),
                'subtotal' => round((float) $rows->sum('subtotal'), 2),
                'total' => round((float) $rows->sum('total'), 2),
            ];
        }

        // Daily trend (created count per day across the window).
        $trend = [];
        $cursor = $s->copy()->startOfDay();
        $grouped = $rentals->groupBy(fn ($r) => $r->created_at->toDateString());
        while ($cursor->lte($e)) {
            $key = $cursor->toDateString();
            $trend[] = [
                'date' => $key,
                'count' => isset($grouped[$key]) ? $grouped[$key]->count() : 0,
            ];
            $cursor->addDay();
        }

        return [
            'total_count' => $rentals->count(),
            'gross' => round((float) $rentals->sum('subtotal'), 2),
            'net' => round((float) $rentals->sum('total'), 2),
            'realized_count' => $rentals->whereIn('status', self::REALIZED_STATUSES)->count(),
            'by_status' => $byStatus,
            'trend' => $trend,
        ];
    }

    /**
     * Top customers by realized rental value in the window.
     */
    public static function topCustomers(?string $start = null, ?string $end = null, int $limit = 10): Collection
    {
        [$s, $e] = self::window($start, $end);

        $rows = Rental::query()
            ->whereBetween('created_at', [$s, $e])
            ->whereIn('status', self::REALIZED_STATUSES)
            ->selectRaw('user_id, COUNT(*) as rental_count, SUM(total) as total_value')
            ->groupBy('user_id')
            ->orderByDesc('total_value')
            ->limit($limit)
            ->get();

        $users = User::whereIn('id', $rows->pluck('user_id')->filter())->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'user_id' => $r->user_id,
            'name' => $users[$r->user_id]->name ?? '—',
            'email' => $users[$r->user_id]->email ?? '',
            'rental_count' => (int) $r->rental_count,
            'total_value' => round((float) $r->total_value, 2),
            'avg_value' => $r->rental_count > 0 ? round((float) $r->total_value / $r->rental_count, 2) : 0.0,
        ]);
    }

    /**
     * Top products by realized rental revenue (line subtotal) in the window.
     */
    public static function topProducts(?string $start = null, ?string $end = null, int $limit = 10): Collection
    {
        [$s, $e] = self::window($start, $end);

        $rows = RentalItem::query()
            ->whereHas('rental', function ($q) use ($s, $e) {
                $q->whereBetween('created_at', [$s, $e])
                    ->whereIn('status', self::REALIZED_STATUSES);
            })
            ->selectRaw('product_id, COUNT(*) as line_count, SUM(subtotal) as revenue, SUM(days) as unit_days')
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->with('product:id,name')
            ->get();

        return $rows->map(fn ($r) => [
            'product_id' => $r->product_id,
            'name' => $r->product->name ?? '—',
            'line_count' => (int) $r->line_count,
            'unit_days' => (int) $r->unit_days,
            'revenue' => round((float) $r->revenue, 2),
        ]);
    }

    /**
     * Late / overdue penalty summary. Late fee is read from the stored
     * rentals.late_fee column (the single source of truth) — never recomputed.
     */
    public static function lateAndPenalty(?string $start = null, ?string $end = null): array
    {
        [$s, $e] = self::window($start, $end);

        $rentals = Rental::query()
            ->whereBetween('created_at', [$s, $e])
            ->where(function ($q) {
                $q->where('late_fee', '>', 0)
                    ->orWhereIn('status', [Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN]);
            })
            ->with('user:id,name')
            ->get();

        $withFee = $rentals->where('late_fee', '>', 0);
        $totalFee = round((float) $rentals->sum('late_fee'), 2);

        $rows = $rentals->sortByDesc('late_fee')->take(50)->map(function (Rental $r) {
            $daysLate = 0;
            if ($r->end_date) {
                $ref = $r->returned_date ?: now();
                $daysLate = max(0, $r->end_date->diffInDays($ref, false));
            }

            return [
                'rental_code' => $r->rental_code,
                'customer' => $r->user->name ?? '—',
                'status' => Rental::getStatusLabel($r->status),
                'end_date' => $r->end_date?->toDateString(),
                'days_late' => $daysLate,
                'late_fee' => round((float) $r->late_fee, 2),
            ];
        })->values();

        return [
            'total_fee' => $totalFee,
            'count_charged' => $withFee->count(),
            'count_late_status' => $rentals->whereIn('status', [Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN])->count(),
            'avg_fee' => $withFee->count() > 0 ? round($totalFee / $withFee->count(), 2) : 0.0,
            'rows' => $rows,
        ];
    }

    /**
     * Discount impact aggregated across the four discount layers via the canonical
     * Rental::discountBreakdown() helper (do NOT re-derive discount lines inline).
     */
    public static function discounts(?string $start = null, ?string $end = null): array
    {
        [$s, $e] = self::window($start, $end);

        $rentals = Rental::query()
            ->whereBetween('created_at', [$s, $e])
            ->whereIn('status', self::REALIZED_STATUSES)
            ->with(['dailyDiscount', 'datePromotion', 'discountRelation'])
            ->get();

        $layers = [
            'category' => ['label' => 'Diskon Kategori', 'amount' => 0.0],
            'daily' => ['label' => 'Promo Harian', 'amount' => 0.0],
            'date' => ['label' => 'Promo Tanggal', 'amount' => 0.0],
            'manual' => ['label' => 'Manual / Kupon', 'amount' => 0.0],
        ];

        foreach ($rentals as $rental) {
            foreach ($rental->discountBreakdown() as $line) {
                if (isset($layers[$line['key']])) {
                    $layers[$line['key']]['amount'] += (float) $line['amount'];
                }
            }
        }

        $gross = round((float) $rentals->sum('subtotal'), 2);
        $totalDiscount = round(array_sum(array_column($layers, 'amount')), 2);

        foreach ($layers as $k => $v) {
            $layers[$k]['amount'] = round($v['amount'], 2);
        }

        return [
            'gross' => $gross,
            'total_discount' => $totalDiscount,
            'discount_ratio' => $gross > 0 ? round(($totalDiscount / $gross) * 100, 1) : 0.0,
            'layers' => array_values(array_map(fn ($k, $v) => ['key' => $k] + $v, array_keys($layers), $layers)),
        ];
    }

    /**
     * Security deposit + down-payment status buckets, and an invoiced-outstanding split.
     */
    public static function depositsAndPayments(?string $start = null, ?string $end = null): array
    {
        [$s, $e] = self::window($start, $end);

        $rentals = Rental::query()
            ->whereBetween('created_at', [$s, $e])
            ->get(['id', 'invoice_id', 'total', 'security_deposit_amount', 'security_deposit_status', 'down_payment_amount', 'down_payment_status']);

        $depositBuckets = $rentals
            ->groupBy(fn ($r) => $r->security_deposit_status ?: 'none')
            ->map(fn ($grp) => [
                'count' => $grp->count(),
                'amount' => round((float) $grp->sum('security_deposit_amount'), 2),
            ]);

        $dpBuckets = $rentals
            ->groupBy(fn ($r) => $r->down_payment_status ?: 'none')
            ->map(fn ($grp) => [
                'count' => $grp->count(),
                'amount' => round((float) $grp->sum('down_payment_amount'), 2),
            ]);

        // Invoiced outstanding: pull the invoices linked to these rentals.
        $invoiceIds = $rentals->pluck('invoice_id')->filter()->unique();
        $invoices = Invoice::whereIn('id', $invoiceIds)->get();
        $outstanding = round($invoices->sum(fn (Invoice $i) => max(0, $i->balance)), 2);
        $paid = round((float) $invoices->sum('paid_amount'), 2);

        return [
            'deposit_held' => round((float) $rentals->where('security_deposit_status', 'held')->sum('security_deposit_amount'), 2),
            'deposit_buckets' => $depositBuckets,
            'dp_buckets' => $dpBuckets,
            'invoiced_count' => $rentals->whereNotNull('invoice_id')->count(),
            'uninvoiced_count' => $rentals->whereNull('invoice_id')->count(),
            'outstanding' => $outstanding,
            'collected' => $paid,
        ];
    }

    /**
     * Average duration, duration distribution, and the quotation→confirmed→completed
     * conversion funnel with cancel/expired ratio.
     */
    public static function durationAndConversion(?string $start = null, ?string $end = null): array
    {
        [$s, $e] = self::window($start, $end);

        $rentals = Rental::query()
            ->whereBetween('created_at', [$s, $e])
            ->get(['id', 'status', 'start_date', 'end_date']);

        $durations = $rentals
            ->filter(fn ($r) => $r->start_date && $r->end_date)
            ->map(fn ($r) => max(1, $r->start_date->diffInDays($r->end_date) + 1));

        $buckets = ['1-2' => 0, '3-5' => 0, '6-7' => 0, '8-14' => 0, '15+' => 0];
        foreach ($durations as $d) {
            $key = match (true) {
                $d <= 2 => '1-2',
                $d <= 5 => '3-5',
                $d <= 7 => '6-7',
                $d <= 14 => '8-14',
                default => '15+',
            };
            $buckets[$key]++;
        }

        $created = $rentals->count();
        // "confirmed or beyond" = anything that got past the quotation stage.
        $everConfirmed = $rentals->whereNotIn('status', [Rental::STATUS_QUOTATION, Rental::STATUS_EXPIRED, Rental::STATUS_CANCELLED])->count();
        $completed = $rentals->where('status', Rental::STATUS_COMPLETED)->count();
        $cancelled = $rentals->where('status', Rental::STATUS_CANCELLED)->count();
        $expired = $rentals->where('status', Rental::STATUS_EXPIRED)->count();

        return [
            'avg_days' => $durations->count() > 0 ? round($durations->avg(), 1) : 0.0,
            'min_days' => $durations->count() > 0 ? (int) $durations->min() : 0,
            'max_days' => $durations->count() > 0 ? (int) $durations->max() : 0,
            'distribution' => $buckets,
            'funnel' => [
                'quotation' => $created,
                'confirmed' => $everConfirmed,
                'completed' => $completed,
            ],
            'conversion_rate' => $created > 0 ? round(($everConfirmed / $created) * 100, 1) : 0.0,
            'completion_rate' => $created > 0 ? round(($completed / $created) * 100, 1) : 0.0,
            'cancelled' => $cancelled,
            'expired' => $expired,
            'lost_ratio' => $created > 0 ? round((($cancelled + $expired) / $created) * 100, 1) : 0.0,
        ];
    }

    /**
     * Delivery (serah-terima) summary: OUT/IN counts, returned-condition distribution,
     * and how many items are still out.
     */
    public static function logistics(?string $start = null, ?string $end = null): array
    {
        [$s, $e] = self::window($start, $end);

        $deliveries = Delivery::query()
            ->whereBetween('date', [$s->toDateString(), $e->toDateString()])
            ->get(['id', 'type', 'status']);

        $conditionRows = DeliveryItem::query()
            ->whereHas('delivery', function ($q) use ($s, $e) {
                $q->where('type', Delivery::TYPE_IN)
                    ->whereBetween('date', [$s->toDateString(), $e->toDateString()]);
            })
            ->whereNotNull('condition')
            ->get(['condition']);

        $conditionDist = $conditionRows
            ->groupBy('condition')
            ->map(fn ($grp) => $grp->count());

        // Items still out: rental items belonging to rentals not yet completed/cancelled.
        $stillOut = RentalItem::query()
            ->whereHas('rental', function ($q) {
                $q->whereIn('status', [
                    Rental::STATUS_ACTIVE,
                    Rental::STATUS_LATE_RETURN,
                    Rental::STATUS_PARTIAL_RETURN,
                ]);
            })
            ->whereNotNull('product_unit_id')
            ->count();

        return [
            'out_count' => $deliveries->where('type', Delivery::TYPE_OUT)->count(),
            'in_count' => $deliveries->where('type', Delivery::TYPE_IN)->count(),
            'condition_distribution' => $conditionDist,
            'issues' => $conditionRows->whereIn('condition', ['fair', 'poor', 'broken', 'lost'])->count(),
            'still_out' => $stillOut,
        ];
    }

    /**
     * Monthly revenue series over the window using revenue_recognized_at (falling back
     * to created_at), with gross (subtotal), net (total), and tax columns per month.
     */
    public static function revenueOverTime(?string $start = null, ?string $end = null): array
    {
        [$s, $e] = self::window($start, $end);

        $rentals = Rental::query()
            ->whereIn('status', self::REALIZED_STATUSES)
            ->where(function ($q) use ($s, $e) {
                $q->whereBetween('revenue_recognized_at', [$s, $e])
                    ->orWhere(function ($qq) use ($s, $e) {
                        $qq->whereNull('revenue_recognized_at')
                            ->whereBetween('created_at', [$s, $e]);
                    });
            })
            ->get(['id', 'subtotal', 'discount', 'total', 'ppn_amount', 'pph_amount', 'revenue_recognized_at', 'created_at']);

        $months = [];
        foreach ($rentals as $r) {
            $anchor = $r->revenue_recognized_at ?: $r->created_at;
            $key = $anchor->format('Y-m');
            if (! isset($months[$key])) {
                $months[$key] = ['month' => $key, 'gross' => 0.0, 'net' => 0.0, 'ppn' => 0.0, 'pph' => 0.0];
            }
            $months[$key]['gross'] += (float) $r->subtotal;
            $months[$key]['net'] += (float) $r->total;
            $months[$key]['ppn'] += (float) $r->ppn_amount;
            $months[$key]['pph'] += (float) $r->pph_amount;
        }

        ksort($months);
        $rows = array_map(function ($m) {
            return [
                'month' => $m['month'],
                'gross' => round($m['gross'], 2),
                'net' => round($m['net'], 2),
                'discount' => round($m['gross'] - $m['net'] + $m['ppn'], 2), // approx: gross - (net - ppn)
                'ppn' => round($m['ppn'], 2),
                'pph' => round($m['pph'], 2),
            ];
        }, array_values($months));

        return [
            'rows' => $rows,
            'total_gross' => round(array_sum(array_column($rows, 'gross')), 2),
            'total_net' => round(array_sum(array_column($rows, 'net')), 2),
            'total_ppn' => round(array_sum(array_column($rows, 'ppn')), 2),
            'total_pph' => round(array_sum(array_column($rows, 'pph')), 2),
        ];
    }
}
