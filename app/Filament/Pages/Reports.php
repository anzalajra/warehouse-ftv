<?php

namespace App\Filament\Pages;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\AgingReportService;
use App\Services\CurrencyService;
use App\Services\InventoryReportService;
use App\Services\RecommendationService;
use App\Services\RentalReportService;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Unified "Report" hub — one page covering Rental, Inventory and Finance reporting,
 * plus a rule-based Recommendation tab that turns every variable into concrete
 * business actions. Finance links out to the existing FinancialReports / LedgerReports
 * (no duplication). Follows the FinancialReports page pattern (Url filter state,
 * month-default date window, array getters, Alpine-tabbed blade, CSV/PDF export).
 */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Report';

    protected static ?string $title = 'Report';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.reports';

    #[Url]
    public string $mainTab = 'recommendations';

    /** this_month | last_month | 3_month | 6_month | yearly | all_time | custom */
    #[Url]
    public string $datePreset = '3_month';

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public string $inventorySearch = '';

    /** Per-request memo so unitMetrics()/productSummary() aren't recomputed per getter. */
    protected array $memo = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function mount(): void
    {
        // Apply the default preset (year-to-date) unless an explicit custom range was
        // passed via the URL. A current-month default previously showed a blank report
        // whenever the latest data was older, hence YTD as the sensible default.
        if (! $this->startDate || ! $this->endDate) {
            $this->applyPreset($this->datePreset === 'custom' ? '3_month' : $this->datePreset);
        }
    }

    /** Preset button handler — sets the concrete date window the services consume. */
    public function setPreset(string $preset): void
    {
        $this->datePreset = $preset;

        if ($preset !== 'custom') {
            $this->applyPreset($preset);
        }
    }

    /** Editing either date picker directly switches the selector to "custom". */
    public function updatedStartDate(): void
    {
        $this->datePreset = 'custom';
    }

    public function updatedEndDate(): void
    {
        $this->datePreset = 'custom';
    }

    protected function applyPreset(string $preset): void
    {
        $now = now();

        [$start, $end] = match ($preset) {
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            '3_month' => [$now->copy()->subMonthsNoOverflow(3)->startOfDay(), $now->copy()->endOfDay()],
            '6_month' => [$now->copy()->subMonthsNoOverflow(6)->startOfDay(), $now->copy()->endOfDay()],
            'all_time' => [$this->allTimeStart(), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfYear(), $now->copy()->endOfDay()], // 'yearly'
        };

        $this->startDate = $start->toDateString();
        $this->endDate = $end->toDateString();
    }

    /** Earliest data date for the "all time" preset (falls back to 3 years back). */
    protected function allTimeStart(): Carbon
    {
        $min = \App\Models\Rental::min('created_at');

        return $min ? Carbon::parse($min)->startOfDay() : now()->subYears(3)->startOfYear();
    }

    // ---- Recommendations ----------------------------------------------------

    public function getRecommendations(): array
    {
        return $this->memo['recs'] ??= RecommendationService::generate($this->startDate, $this->endDate);
    }

    public function getRecommendationCounts(): array
    {
        return RecommendationService::countsByDomain($this->getRecommendations());
    }

    // ---- Rental getters -----------------------------------------------------

    public function getRentalSummary(): array
    {
        return RentalReportService::summary($this->startDate, $this->endDate);
    }

    public function getTopCustomers(): Collection
    {
        return RentalReportService::topCustomers($this->startDate, $this->endDate);
    }

    public function getTopProducts(): Collection
    {
        return RentalReportService::topProducts($this->startDate, $this->endDate);
    }

    public function getLatePenalty(): array
    {
        return RentalReportService::lateAndPenalty($this->startDate, $this->endDate);
    }

    public function getDiscounts(): array
    {
        return RentalReportService::discounts($this->startDate, $this->endDate);
    }

    public function getDepositsPayments(): array
    {
        return RentalReportService::depositsAndPayments($this->startDate, $this->endDate);
    }

    public function getDurationConversion(): array
    {
        return RentalReportService::durationAndConversion($this->startDate, $this->endDate);
    }

    public function getLogistics(): array
    {
        return RentalReportService::logistics($this->startDate, $this->endDate);
    }

    public function getRevenueOverTime(): array
    {
        return RentalReportService::revenueOverTime($this->startDate, $this->endDate);
    }

    // ---- Inventory getters --------------------------------------------------

    public function getStockStatus(): array
    {
        return $this->memo['stock'] ??= InventoryReportService::stockStatus();
    }

    protected function unitMetrics(): Collection
    {
        return $this->memo['units'] ??= InventoryReportService::unitMetrics($this->startDate, $this->endDate);
    }

    protected function filteredUnits(): Collection
    {
        $units = $this->unitMetrics();
        $search = trim($this->inventorySearch);
        if ($search === '') {
            return $units;
        }

        $needle = mb_strtolower($search);

        return $units->filter(fn ($u) => str_contains(mb_strtolower($u['name']), $needle)
            || str_contains(mb_strtolower((string) $u['serial']), $needle))->values();
    }

    public function getUtilizationRows(): Collection
    {
        return $this->filteredUnits()->sortByDesc('utilization_rate')->take(50)->values();
    }

    public function getMaintenanceRows(): Collection
    {
        return $this->filteredUnits()
            ->filter(fn ($u) => $u['maintenance_freq'] > 0 || $u['period_maintenance'] > 0)
            ->sortByDesc('period_maintenance')->take(50)->values();
    }

    public function getDepreciationRows(): Collection
    {
        return $this->filteredUnits()->sortByDesc('purchase_price')->take(50)->values();
    }

    public function getLostDamaged(): Collection
    {
        return InventoryReportService::lostDamaged();
    }

    public function getPerformanceProducts(): Collection
    {
        return InventoryReportService::productPerformance($this->startDate, $this->endDate);
    }

    public function getPerformanceUnits(): Collection
    {
        return $this->filteredUnits()->sortByDesc('period_revenue')->take(50)->values();
    }

    public function getDepreciationTotals(): array
    {
        return InventoryReportService::depreciationTotals();
    }

    // ---- Finance getters (links out to existing finance reports) ------------

    public function getFinanceKpis(): array
    {
        [$s, $e] = [
            Carbon::parse($this->startDate)->startOfDay(),
            Carbon::parse($this->endDate)->endOfDay(),
        ];

        $rental = $this->getRentalSummary();
        $aging = AgingReportService::receivables($this->endDate);
        $deposits = $this->getDepositsPayments();

        $income = (float) Invoice::whereBetween('date', [$s, $e])->sum('total');
        $expense = (float) Bill::whereBetween('bill_date', [$s, $e])->sum('amount')
            + (float) Expense::whereBetween('date', [$s, $e])->sum('amount');

        return [
            'rental_net' => $rental['net'],
            'ar_outstanding' => $aging['grand_total'] ?? 0.0,
            'deposit_held' => $deposits['deposit_held'],
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income - $expense, 2),
        ];
    }

    public function getFinanceLinks(): array
    {
        $links = [
            [
                'label' => 'Laporan Operasional (P&L, Neraca, AR, Pajak, Aset)',
                'url' => \App\Filament\Clusters\Finance\Pages\FinancialReports::getUrl(),
                'icon' => 'heroicon-o-chart-pie',
            ],
        ];

        if (Setting::get('finance_mode', 'simple') === 'advanced') {
            $links[] = [
                'label' => 'Laporan GL (Trial Balance, Income Statement, Neraca)',
                'url' => \App\Filament\Clusters\Finance\Pages\LedgerReports::getUrl(),
                'icon' => 'heroicon-o-book-open',
            ];
        }

        return $links;
    }

    // ---- Export -------------------------------------------------------------

    public function export(string $section, string $format = 'csv')
    {
        [$headers, $rows, $title] = $this->exportDataset($section);

        $filename = 'report-'.$section.'-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('filament.pages.reports.generic-pdf', [
                'title' => $title,
                'headers' => $headers,
                'rows' => $rows,
                'period' => $this->startDate.' — '.$this->endDate,
                'date' => now()->format('d M Y'),
            ]);

            return response()->streamDownload(fn () => print ($pdf->output()), $filename.'.pdf');
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // BOM for Excel
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, $filename.'.csv');
    }

    /**
     * @return array{0: array<string>, 1: array<array>, 2: string}
     */
    protected function exportDataset(string $section): array
    {
        switch ($section) {
            case 'recommendations':
                $rows = array_map(fn ($r) => [
                    $r['priority'], $r['domain'], $r['title'], $r['reason'], $r['action'],
                ], $this->getRecommendations());

                return [['Prioritas', 'Domain', 'Rekomendasi', 'Alasan', 'Tindakan'], $rows, 'Rekomendasi Bisnis'];

            case 'rental_summary':
                $rows = array_map(fn ($s) => [
                    $s['label'], $s['count'], $s['subtotal'], $s['total'],
                ], $this->getRentalSummary()['by_status']);

                return [['Status', 'Jumlah', 'Subtotal', 'Total'], $rows, 'Ringkasan Rental'];

            case 'top_customers':
                $rows = $this->getTopCustomers()->map(fn ($c) => [
                    $c['name'], $c['email'], $c['rental_count'], $c['total_value'], $c['avg_value'],
                ])->toArray();

                return [['Pelanggan', 'Email', 'Jumlah Sewa', 'Total', 'Rata-rata'], $rows, 'Top Pelanggan'];

            case 'top_products':
                $rows = $this->getTopProducts()->map(fn ($p) => [
                    $p['name'], $p['line_count'], $p['unit_days'], $p['revenue'],
                ])->toArray();

                return [['Produk', 'Baris Sewa', 'Total Hari', 'Pendapatan'], $rows, 'Top Produk'];

            case 'late':
                $rows = $this->getLatePenalty()['rows']->map(fn ($r) => [
                    $r['rental_code'], $r['customer'], $r['status'], $r['end_date'], $r['days_late'], $r['late_fee'],
                ])->toArray();

                return [['Kode', 'Pelanggan', 'Status', 'Selesai', 'Hari Telat', 'Denda'], $rows, 'Keterlambatan & Denda'];

            case 'discounts':
                $rows = array_map(fn ($l) => [$l['label'], $l['amount']], $this->getDiscounts()['layers']);

                return [['Layer Diskon', 'Jumlah'], $rows, 'Diskon & Promosi'];

            case 'revenue':
                $rows = array_map(fn ($m) => [
                    $m['month'], $m['gross'], $m['net'], $m['ppn'], $m['pph'],
                ], $this->getRevenueOverTime()['rows']);

                return [['Bulan', 'Kotor', 'Bersih', 'PPN', 'PPh'], $rows, 'Revenue per Waktu'];

            case 'stock':
                $rows = $this->getStockStatus()['by_product']->map(fn ($p) => [
                    $p['product'], $p['total'], $p['available'], $p['rented'], $p['scheduled'], $p['maintenance'], $p['retired'],
                ])->toArray();

                return [['Produk', 'Total', 'Tersedia', 'Disewa', 'Terjadwal', 'Maintenance', 'Pensiun'], $rows, 'Status & Stok Unit'];

            case 'utilization':
                $rows = $this->getUtilizationRows()->map(fn ($u) => [
                    $u['name'], $u['days_rented'], $u['utilization_rate'], $u['period_revenue'],
                ])->toArray();

                return [['Unit', 'Hari Tersewa', 'Utilisasi %', 'Pendapatan'], $rows, 'Utilisasi Unit'];

            case 'maintenance':
                $rows = $this->getMaintenanceRows()->map(fn ($u) => [
                    $u['name'], $u['maintenance_freq'], $u['period_maintenance'], $u['lifetime_maintenance'], $u['profitability'],
                ])->toArray();

                return [['Unit', 'Frekuensi', 'Biaya Periode', 'Biaya Total', 'Profitabilitas'], $rows, 'Maintenance & Kerusakan'];

            case 'depreciation':
                $rows = $this->getDepreciationRows()->map(fn ($u) => [
                    $u['name'], $u['purchase_price'], $u['accumulated_depreciation'], $u['book_value'], $u['residual_value'],
                ])->toArray();

                return [['Unit', 'Harga Beli', 'Akumulasi Depresiasi', 'Nilai Buku', 'Nilai Residu'], $rows, 'Depresiasi & Nilai Aset'];

            case 'performance_products':
                $rows = $this->getPerformanceProducts()->map(fn ($p) => [
                    $p['product'], $p['unit_count'], $p['avg_utilization'], $p['total_days'], $p['period_revenue'], $p['revenue_per_unit'], $p['avg_roi'],
                ])->toArray();

                return [['Produk', 'Jumlah Unit', 'Utilisasi %', 'Total Hari', 'Pendapatan', 'Pendapatan/Unit', 'ROI %'], $rows, 'Performa Produk'];

            case 'performance_units':
                $rows = $this->getPerformanceUnits()->map(fn ($u) => [
                    $u['name'], $u['utilization_rate'], $u['days_rented'], $u['period_revenue'], $u['lifetime_revenue'], $u['roi'],
                ])->toArray();

                return [['Unit', 'Utilisasi %', 'Hari Tersewa', 'Pendapatan Periode', 'Pendapatan Total', 'ROI %'], $rows, 'Performa per Unit'];

            default:
                return [['Info'], [['Bagian tidak dikenali: '.$section]], 'Report'];
        }
    }

    /** Currency formatting shortcut for the blade. */
    public function money(float $amount): string
    {
        return CurrencyService::format($amount);
    }
}
