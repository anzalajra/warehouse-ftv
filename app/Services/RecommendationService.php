<?php

namespace App\Services;

use App\Models\FinanceTransaction;
use Carbon\Carbon;

/**
 * Deterministic business-recommendation engine ("rumus rekomendasi").
 *
 * Reads the aggregated variables produced by {@see RentalReportService},
 * {@see InventoryReportService}, {@see AgingReportService} and finance data for a
 * period, applies a set of transparent rule thresholds, and emits prioritized,
 * actionable recommendations for the admin (turunkan harga, beli unit baru, buat
 * promo, pensiunkan unit, tagih piutang, …).
 *
 * No LLM / randomness — every recommendation is traceable to the numbers that
 * triggered it (the `reason`/`metric` fields), so it is auditable. Thresholds are
 * constants here for now; they can later be surfaced as Setting keys for tuning.
 *
 * Each recommendation:
 *   ['key','domain','priority','title','reason','action','metric','link']
 *   domain   : pricing|inventory|promo|maintenance|collections|retention|finance
 *   priority : high|medium|low
 */
class RecommendationService
{
    // ---- Tunable thresholds -------------------------------------------------
    public const LOW_UTIL_PCT = 20.0;          // product avg utilization considered "underused"
    public const HIGH_UTIL_PCT = 80.0;         // product avg utilization considered "in high demand"
    public const MIN_UNITS_FOR_SIGNAL = 2;     // ignore products with too few units to be meaningful
    public const REVENUE_DROP_PCT = 15.0;      // period-over-period net revenue drop to flag
    public const HIGH_DISCOUNT_PCT = 25.0;     // total discount as % of gross to flag as excessive
    public const AR_OVERDUE_DAYS = 90;         // aging bucket that triggers a collections push
    public const RESIDUAL_FACTOR = 1.1;        // book value <= residual × this ⇒ near end-of-life
    public const HIGH_MAINT_FREQ = 3;          // maintenance events in period considered high
    public const LATE_INCIDENCE_PCT = 15.0;    // % of rentals late ⇒ tighten terms
    public const LOW_CONVERSION_PCT = 40.0;    // quotation→confirmed rate below this ⇒ act
    public const CUSTOMER_CONCENTRATION_PCT = 50.0; // top-3 share of revenue ⇒ retention risk
    public const MAX_UNIT_ROWS = 5;            // cap per-unit recommendation spam

    protected const PRIORITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    /**
     * Produce the full, priority-sorted recommendation list for the window.
     */
    public static function generate(?string $start = null, ?string $end = null): array
    {
        $recs = array_merge(
            self::inventoryRules($start, $end),
            self::rentalRules($start, $end),
            self::financeRules($start, $end),
        );

        usort($recs, function ($a, $b) {
            $pa = self::PRIORITY_ORDER[$a['priority']] ?? 9;
            $pb = self::PRIORITY_ORDER[$b['priority']] ?? 9;

            return $pa <=> $pb;
        });

        return $recs;
    }

    /**
     * Count recommendations per domain (for the tab badges).
     */
    public static function countsByDomain(array $recs): array
    {
        $counts = [];
        foreach ($recs as $r) {
            $counts[$r['domain']] = ($counts[$r['domain']] ?? 0) + 1;
        }

        return $counts;
    }

    // ---- Inventory / utilization rules --------------------------------------

    protected static function inventoryRules(?string $start, ?string $end): array
    {
        $recs = [];
        $products = InventoryReportService::productSummary($start, $end);

        foreach ($products as $p) {
            if ($p['unit_count'] < self::MIN_UNITS_FOR_SIGNAL) {
                continue;
            }

            // Underused product with idle stock → cut price or run a promo.
            if ($p['avg_utilization'] < self::LOW_UTIL_PCT && $p['idle_units'] >= 1) {
                $recs[] = [
                    'key' => 'low_util_'.$p['product_id'],
                    'domain' => 'pricing',
                    'priority' => 'medium',
                    'title' => 'Turunkan harga / buat promo: '.$p['product'],
                    'reason' => "Utilisasi rata-rata hanya {$p['avg_utilization']}% dengan {$p['idle_units']} unit menganggur di periode ini.",
                    'action' => 'Pertimbangkan menurunkan tarif harian atau membuat DailyDiscount/DatePromotion agar permintaan naik.',
                    'metric' => "Utilisasi {$p['avg_utilization']}% · {$p['idle_units']} unit idle · {$p['unit_count']} unit",
                    'link' => null,
                ];
            }

            // High-demand product → add units (and consider a price increase).
            if ($p['avg_utilization'] >= self::HIGH_UTIL_PCT) {
                $recs[] = [
                    'key' => 'high_util_'.$p['product_id'],
                    'domain' => 'inventory',
                    'priority' => 'high',
                    'title' => 'Beli unit baru: '.$p['product'],
                    'reason' => "Utilisasi rata-rata {$p['avg_utilization']}% — permintaan mendekati/melebihi kapasitas ({$p['unit_count']} unit).",
                    'action' => 'Tambah stok unit. Bila sering penuh, pertimbangkan menaikkan tarif harian karena permintaan > pasokan.',
                    'metric' => "Utilisasi {$p['avg_utilization']}% · {$p['unit_count']} unit",
                    'link' => null,
                ];
            }
        }

        // Per-unit: maintenance cost outweighs revenue → retire/replace.
        $units = InventoryReportService::unitMetrics($start, $end);

        $unprofitable = $units
            ->filter(fn ($u) => $u['profitability'] < 0 && $u['lifetime_maintenance'] > 0)
            ->sortBy('profitability')
            ->take(self::MAX_UNIT_ROWS);

        foreach ($unprofitable as $u) {
            $recs[] = [
                'key' => 'retire_'.$u['unit_id'],
                'domain' => 'maintenance',
                'priority' => 'medium',
                'title' => 'Pensiunkan / ganti unit: '.$u['name'],
                'reason' => 'Biaya perawatan seumur hidup ('.CurrencyService::format($u['lifetime_maintenance']).') melebihi kontribusi unit (profitabilitas '.CurrencyService::format($u['profitability']).').',
                'action' => 'Evaluasi untuk pensiun/jual, atau jadwalkan perawatan preventif bila masih layak.',
                'metric' => 'Profitabilitas '.CurrencyService::format($u['profitability']).' · '.$u['maintenance_freq'].'× maintenance',
                'link' => self::productLink($u['product_id']),
            ];
        }

        // Per-unit: book value near residual → plan replacement.
        $nearEol = $units
            ->filter(fn ($u) => $u['residual_value'] > 0
                && $u['status'] !== \App\Models\ProductUnit::STATUS_RETIRED
                && $u['book_value'] <= $u['residual_value'] * self::RESIDUAL_FACTOR)
            ->sortBy('book_value')
            ->take(self::MAX_UNIT_ROWS);

        foreach ($nearEol as $u) {
            $recs[] = [
                'key' => 'eol_'.$u['unit_id'],
                'domain' => 'inventory',
                'priority' => 'low',
                'title' => 'Rencanakan peremajaan aset: '.$u['name'],
                'reason' => 'Nilai buku ('.CurrencyService::format($u['book_value']).') sudah mendekati nilai residu ('.CurrencyService::format($u['residual_value']).').',
                'action' => 'Anggarkan penggantian unit sebelum performa/keandalan menurun.',
                'metric' => 'Nilai buku '.CurrencyService::format($u['book_value']),
                'link' => self::productLink($u['product_id']),
            ];
        }

        return $recs;
    }

    // ---- Rental / revenue rules ---------------------------------------------

    protected static function rentalRules(?string $start, ?string $end): array
    {
        $recs = [];

        [$s, $e] = self::resolveWindow($start, $end);
        $lengthDays = max(1, $s->diffInDays($e) + 1);
        $priorStart = $s->copy()->subDays($lengthDays)->toDateString();
        $priorEnd = $s->copy()->subDay()->toDateString();

        $current = RentalReportService::summary($start, $end);
        $prior = RentalReportService::summary($priorStart, $priorEnd);

        // Revenue drop vs previous equal-length period → promo/marketing.
        if ($prior['net'] > 0) {
            $delta = round((($current['net'] - $prior['net']) / $prior['net']) * 100, 1);
            if ($delta <= -self::REVENUE_DROP_PCT) {
                $recs[] = [
                    'key' => 'revenue_drop',
                    'domain' => 'promo',
                    'priority' => 'high',
                    'title' => 'Buat promo / dorong pemasaran',
                    'reason' => "Pendapatan turun {$delta}% dibanding periode sebelumnya (".CurrencyService::format($prior['net']).' → '.CurrencyService::format($current['net']).').',
                    'action' => 'Jalankan promo (DatePromotion/kupon) atau kampanye pemasaran untuk mengangkat permintaan.',
                    'metric' => "Δ {$delta}% · ".CurrencyService::format($current['net']),
                    'link' => null,
                ];
            }
        }

        // Excessive discount ratio → tighten promo rules.
        $disc = RentalReportService::discounts($start, $end);
        if ($disc['discount_ratio'] > self::HIGH_DISCOUNT_PCT) {
            $recs[] = [
                'key' => 'high_discount',
                'domain' => 'pricing',
                'priority' => 'medium',
                'title' => 'Perketat aturan diskon',
                'reason' => "Total diskon mencapai {$disc['discount_ratio']}% dari nilai kotor (".CurrencyService::format($disc['total_discount']).').',
                'action' => 'Tinjau ambang promo/kupon dan diskon kategori agar margin tidak tergerus.',
                'metric' => "Diskon {$disc['discount_ratio']}% dari gross",
                'link' => null,
            ];
        }

        // High late incidence / rising late fees → raise deposit / tighten terms.
        $late = RentalReportService::lateAndPenalty($start, $end);
        $totalRentals = max(1, $current['total_count']);
        $lateIncidence = round((($late['count_charged'] + $late['count_late_status']) / $totalRentals) * 100, 1);
        if ($lateIncidence >= self::LATE_INCIDENCE_PCT && $late['total_fee'] > 0) {
            $recs[] = [
                'key' => 'late_incidence',
                'domain' => 'pricing',
                'priority' => 'medium',
                'title' => 'Naikkan deposit / perketat syarat sewa',
                'reason' => "Insiden keterlambatan {$lateIncidence}% dengan total denda ".CurrencyService::format($late['total_fee']).'.',
                'action' => 'Pertimbangkan menaikkan persentase deposit atau memperketat syarat pengembalian.',
                'metric' => "Telat {$lateIncidence}% · denda ".CurrencyService::format($late['total_fee']),
                'link' => null,
            ];
        }

        // Low conversion / many expired → follow up quotes faster.
        $conv = RentalReportService::durationAndConversion($start, $end);
        if ($current['total_count'] >= 5 && $conv['conversion_rate'] < self::LOW_CONVERSION_PCT) {
            $recs[] = [
                'key' => 'low_conversion',
                'domain' => 'promo',
                'priority' => 'medium',
                'title' => 'Percepat follow-up & tinjau harga penawaran',
                'reason' => "Konversi penawaran hanya {$conv['conversion_rate']}% ({$conv['expired']} kedaluwarsa, {$conv['cancelled']} dibatalkan).",
                'action' => 'Percepat konfirmasi penawaran (WA/telepon) dan tinjau harga di tahap quote.',
                'metric' => "Konversi {$conv['conversion_rate']}%",
                'link' => null,
            ];
        }

        // Customer concentration → loyalty/retention.
        $topCustomers = RentalReportService::topCustomers($start, $end, 3);
        $top3Value = $topCustomers->sum('total_value');
        if ($current['net'] > 0 && $top3Value > 0) {
            $share = round(($top3Value / $current['net']) * 100, 1);
            if ($share >= self::CUSTOMER_CONCENTRATION_PCT) {
                $recs[] = [
                    'key' => 'customer_concentration',
                    'domain' => 'retention',
                    'priority' => 'low',
                    'title' => 'Perkuat loyalitas & retensi pelanggan',
                    'reason' => "Top-3 pelanggan menyumbang {$share}% pendapatan — konsentrasi tinggi, berisiko bila salah satu pergi.",
                    'action' => 'Buat program loyalitas/retensi dan diversifikasi basis pelanggan.',
                    'metric' => "Top-3 = {$share}% revenue",
                    'link' => null,
                ];
            }
        }

        return $recs;
    }

    // ---- Finance rules ------------------------------------------------------

    protected static function financeRules(?string $start, ?string $end): array
    {
        $recs = [];

        // Overdue receivables (> 90 days) → collections push.
        $aging = AgingReportService::receivables($end);
        $over90 = (float) ($aging['totals']['over_90'] ?? 0);
        if ($over90 > 0) {
            $recs[] = [
                'key' => 'ar_over_90',
                'domain' => 'collections',
                'priority' => 'high',
                'title' => 'Tagih piutang jatuh tempo > 90 hari',
                'reason' => 'Terdapat piutang '.CurrencyService::format($over90).' yang menunggak lebih dari 90 hari.',
                'action' => 'Prioritaskan penagihan (reminder/telepon) dan tinjau kebijakan kredit pelanggan terkait.',
                'metric' => '> 90 hari: '.CurrencyService::format($over90),
                'link' => null,
            ];
        }

        // Negative operating cash flow in the period → cost control.
        [$s, $e] = self::resolveWindow($start, $end);
        $inflow = (float) FinanceTransaction::whereBetween('date', [$s, $e])
            ->whereIn('type', [FinanceTransaction::TYPE_INCOME, FinanceTransaction::TYPE_DEPOSIT_IN])
            ->sum('amount');
        $outflow = (float) FinanceTransaction::whereBetween('date', [$s, $e])
            ->whereIn('type', [FinanceTransaction::TYPE_EXPENSE, FinanceTransaction::TYPE_DEPOSIT_OUT])
            ->sum('amount');
        $netCash = $inflow - $outflow;
        if ($netCash < 0) {
            $recs[] = [
                'key' => 'negative_cash_flow',
                'domain' => 'finance',
                'priority' => 'high',
                'title' => 'Arus kas periode negatif — kendalikan biaya',
                'reason' => 'Arus kas bersih periode ini '.CurrencyService::format($netCash).' (masuk '.CurrencyService::format($inflow).', keluar '.CurrencyService::format($outflow).').',
                'action' => 'Tinjau pengeluaran, percepat penagihan, dan tunda belanja tidak mendesak.',
                'metric' => 'Net kas '.CurrencyService::format($netCash),
                'link' => null,
            ];
        }

        return $recs;
    }

    // ---- helpers ------------------------------------------------------------

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function resolveWindow(?string $start, ?string $end): array
    {
        $s = $start ? Carbon::parse($start)->startOfDay() : now()->startOfMonth();
        $e = $end ? Carbon::parse($end)->endOfDay() : now()->endOfMonth();

        return [$s, $e];
    }

    protected static function productLink(?int $productId): ?array
    {
        if (! $productId) {
            return null;
        }

        // Built directly (not via ProductResource::getUrl) — stable admin path.
        return ['label' => 'Buka produk', 'url' => url('/admin/products/'.$productId.'/edit')];
    }
}
