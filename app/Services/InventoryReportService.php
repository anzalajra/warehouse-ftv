<?php

namespace App\Services;

use App\Models\ProductUnit;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * INVENTORY / WAREHOUSE reporting aggregates for the unified Report page.
 *
 * Centralizes the per-unit metric math (utilization / revenue / maintenance /
 * depreciation / lost-damaged) that historically lived inline in
 * {@see \App\Filament\Clusters\Finance\Pages\FinancialReports::getAssetMetrics()}.
 * Reuses ProductUnit's own computed helpers (current_value, calculateTotalRevenue,
 * calculateTotalMaintenanceCost, calculateProfitability) rather than duplicating them.
 */
class InventoryReportService
{
    /** Per-request memo for unitMetrics(), keyed by the date window. */
    protected static array $unitCache = [];

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
     * Snapshot of unit counts by status, plus per-product and per-warehouse breakdowns.
     */
    public static function stockStatus(): array
    {
        $units = ProductUnit::query()
            ->with(['product:id,name', 'warehouse:id,name'])
            ->get(['id', 'product_id', 'warehouse_id', 'status', 'condition']);

        $totals = [];
        foreach (array_keys(ProductUnit::getStatusOptions()) as $status) {
            $totals[$status] = $units->where('status', $status)->count();
        }

        $byProduct = $units->groupBy('product_id')->map(function ($grp) {
            $first = $grp->first();

            return [
                'product' => $first->product->name ?? '—',
                'total' => $grp->count(),
                'available' => $grp->where('status', ProductUnit::STATUS_AVAILABLE)->count(),
                'rented' => $grp->where('status', ProductUnit::STATUS_RENTED)->count(),
                'scheduled' => $grp->where('status', ProductUnit::STATUS_SCHEDULED)->count(),
                'maintenance' => $grp->where('status', ProductUnit::STATUS_MAINTENANCE)->count(),
                'retired' => $grp->where('status', ProductUnit::STATUS_RETIRED)->count(),
            ];
        })->sortByDesc('total')->values();

        $byWarehouse = $units->groupBy('warehouse_id')->map(function ($grp) {
            $first = $grp->first();

            return [
                'warehouse' => $first->warehouse->name ?? '—',
                'total' => $grp->count(),
                'available' => $grp->where('status', ProductUnit::STATUS_AVAILABLE)->count(),
                'rented' => $grp->where('status', ProductUnit::STATUS_RENTED)->count(),
                'maintenance' => $grp->where('status', ProductUnit::STATUS_MAINTENANCE)->count(),
            ];
        })->sortByDesc('total')->values();

        return [
            'total_units' => $units->count(),
            'totals' => $totals,
            'by_product' => $byProduct,
            'by_warehouse' => $byWarehouse,
        ];
    }

    /**
     * Per-unit metrics over the window. Single source that powers the utilization,
     * maintenance, and depreciation sub-views as well as the recommendation engine.
     *
     * @return Collection<int, array>
     */
    public static function unitMetrics(?string $start = null, ?string $end = null): Collection
    {
        [$s, $e] = self::window($start, $end);
        $cacheKey = $s->toDateString().'|'.$e->toDateString();
        if (isset(self::$unitCache[$cacheKey])) {
            return self::$unitCache[$cacheKey];
        }

        $daysInPeriod = max(1, $s->diffInDays($e) + 1);
        $sDate = $s->toDateString();
        $eDate = $e->toDateString();

        // All figures resolved as SQL aggregate subqueries (withSum/withCount) — one
        // query for every unit, no per-unit method calls (which previously caused an
        // N+1 storm through calculateTotalRevenue/Maintenance/Profitability).
        $periodItem = fn ($q) => $q->whereBetween('created_at', [$s, $e])
            ->whereHas('rental', fn ($r) => $r->whereNotIn('status', [Rental::STATUS_CANCELLED, Rental::STATUS_EXPIRED]));
        $lifetimeItem = fn ($q) => $q->whereHas('rental', fn ($r) => $r->whereNotIn('status', [Rental::STATUS_CANCELLED]));
        $periodMaint = fn ($q) => $q->whereBetween('date', [$sDate, $eDate]);

        $units = ProductUnit::query()
            ->with('product:id,name')
            ->withSum(['rentalItems as period_days' => $periodItem], 'days')
            ->withSum(['rentalItems as period_revenue' => $periodItem], 'subtotal')
            ->withSum(['rentalItems as lifetime_revenue' => $lifetimeItem], 'subtotal')
            ->withSum(['maintenanceRecords as period_maintenance' => $periodMaint], 'cost')
            ->withCount(['maintenanceRecords as maintenance_freq' => $periodMaint])
            ->withSum('maintenanceRecords as lifetime_maintenance', 'cost')
            ->get();

        $result = $units->map(function (ProductUnit $unit) use ($daysInPeriod) {
            $daysRented = (int) ($unit->period_days ?? 0);
            $utilization = min(100.0, round(($daysRented / $daysInPeriod) * 100, 1));

            $periodRevenue = round((float) ($unit->period_revenue ?? 0), 2);
            $lifetimeRevenue = round((float) ($unit->lifetime_revenue ?? 0), 2);
            $lifetimeMaintenance = round((float) ($unit->lifetime_maintenance ?? 0), 2);

            $purchase = (float) ($unit->purchase_price ?? 0);
            $residual = (float) ($unit->residual_value ?? 0);
            $bookValue = (float) $unit->current_value;
            $accumulated = round(max(0, $purchase - $bookValue), 2);

            return [
                'unit_id' => $unit->id,
                'serial' => $unit->serial_number,
                'product_id' => $unit->product_id,
                'name' => ($unit->product->name ?? 'Unknown').' ('.$unit->serial_number.')',
                'status' => $unit->status,
                'condition' => $unit->condition,
                'days_rented' => $daysRented,
                'utilization_rate' => $utilization,
                'period_revenue' => $periodRevenue,
                'lifetime_revenue' => $lifetimeRevenue,
                'period_maintenance' => round((float) ($unit->period_maintenance ?? 0), 2),
                'maintenance_freq' => (int) ($unit->maintenance_freq ?? 0),
                'lifetime_maintenance' => $lifetimeMaintenance,
                'profitability' => round($lifetimeRevenue - $lifetimeMaintenance - $purchase, 2),
                'roi' => $purchase > 0 ? round(($lifetimeRevenue / $purchase) * 100, 1) : 0.0,
                'purchase_price' => round($purchase, 2),
                'residual_value' => round($residual, 2),
                'accumulated_depreciation' => $accumulated,
                'book_value' => round($bookValue, 2),
            ];
        })->values();

        return self::$unitCache[$cacheKey] = $result;
    }

    /**
     * Product-level rollup of unitMetrics — used by the recommendation engine
     * (avg utilization, unit count, idle count, revenue, maintenance).
     *
     * @return Collection<int, array>
     */
    public static function productSummary(?string $start = null, ?string $end = null): Collection
    {
        $metrics = self::unitMetrics($start, $end);

        return $metrics->groupBy('product_id')->map(function ($grp) {
            $productName = explode(' (', $grp->first()['name'])[0];
            $unitCount = $grp->count();
            $avgUtil = round($grp->avg('utilization_rate'), 1);

            return [
                'product_id' => $grp->first()['product_id'],
                'product' => $productName,
                'unit_count' => $unitCount,
                'avg_utilization' => $avgUtil,
                'idle_units' => $grp->where('utilization_rate', 0)->count(),
                'active_units' => $grp->where('status', ProductUnit::STATUS_RENTED)->count(),
                'period_revenue' => round($grp->sum('period_revenue'), 2),
                'period_maintenance' => round($grp->sum('period_maintenance'), 2),
            ];
        })->sortByDesc('period_revenue')->values();
    }

    /**
     * Product Performance report — utilization + revenue per product (and revenue per
     * unit), sorted by period revenue. The per-unit rows live in unitMetrics().
     *
     * @return Collection<int, array>
     */
    public static function productPerformance(?string $start = null, ?string $end = null): Collection
    {
        return self::unitMetrics($start, $end)->groupBy('product_id')->map(function ($grp) {
            $productName = explode(' (', $grp->first()['name'])[0];
            $unitCount = $grp->count();
            $periodRevenue = round($grp->sum('period_revenue'), 2);

            return [
                'product_id' => $grp->first()['product_id'],
                'product' => $productName,
                'unit_count' => $unitCount,
                'avg_utilization' => round($grp->avg('utilization_rate'), 1),
                'total_days' => (int) $grp->sum('days_rented'),
                'period_revenue' => $periodRevenue,
                'revenue_per_unit' => $unitCount > 0 ? round($periodRevenue / $unitCount, 2) : 0.0,
                'lifetime_revenue' => round($grp->sum('lifetime_revenue'), 2),
                'avg_roi' => round($grp->avg('roi'), 1),
            ];
        })->sortByDesc('period_revenue')->values();
    }

    /**
     * Retired units written off as lost/broken, with the book-value loss at retirement.
     *
     * @return Collection<int, array>
     */
    public static function lostDamaged(): Collection
    {
        return ProductUnit::query()
            ->with('product:id,name')
            ->whereIn('condition', ['lost', 'broken'])
            ->where('status', ProductUnit::STATUS_RETIRED)
            ->get()
            ->map(function (ProductUnit $unit) {
                $purchase = (float) ($unit->purchase_price ?? 0);
                $bookValue = (float) $unit->current_value;

                return [
                    'name' => ($unit->product->name ?? 'Unknown').' ('.$unit->serial_number.')',
                    'condition' => ucfirst((string) $unit->condition),
                    'date_reported' => $unit->updated_at?->toDateString(),
                    'purchase_price' => round($purchase, 2),
                    'book_value_loss' => round($bookValue, 2),
                ];
            })
            ->values();
    }

    /**
     * Portfolio depreciation totals across all non-retired units.
     */
    public static function depreciationTotals(): array
    {
        $units = ProductUnit::query()
            ->where('status', '!=', ProductUnit::STATUS_RETIRED)
            ->get();

        $cost = 0.0;
        $book = 0.0;
        foreach ($units as $unit) {
            $cost += (float) ($unit->purchase_price ?? 0);
            $book += (float) $unit->current_value;
        }

        return [
            'total_cost' => round($cost, 2),
            'total_book_value' => round($book, 2),
            'accumulated_depreciation' => round(max(0, $cost - $book), 2),
            'unit_count' => $units->count(),
        ];
    }
}
