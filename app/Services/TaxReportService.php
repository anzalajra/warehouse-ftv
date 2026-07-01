<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;

class TaxReportService
{
    /**
     * Generate Tax Report for a given period.
     * Calculates PPN Output (Pajak Keluaran) from Invoices.
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public static function generate(Carbon $startDate, Carbon $endDate): array
    {
        // Query Invoices within the period
        // Logic: Tax is usually reported based on Invoice Date (Factur Pajak Date)
        $invoices = Invoice::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_taxable', true)
            ->whereNotIn('status', ['draft', 'cancelled']) // Exclude Drafts and Cancelled
            ->get();

        $totalPpn = $invoices->sum('ppn_amount');
        $totalTaxBase = $invoices->sum('subtotal'); // Assuming subtotal is DPP (Dasar Pengenaan Pajak)
        $totalTotal = $invoices->sum('total');

        return [
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'total_tax_base' => $totalTaxBase,
            'total_ppn_payable' => $totalPpn, // This matches the test expectation
            'total_sales' => $totalTotal,
            'transaction_count' => $invoices->count(),
        ];
    }

    /**
     * Per-invoice PPN Keluaran (output tax) detail for a period — the data foundation
     * for a Faktur Pajak / e-Faktur export. Each row carries the customer identity,
     * DPP (tax base), and PPN so it can be reconciled against DJP.
     *
     * @return array{lines: array, total_dpp: float, total_ppn: float}
     */
    public static function outputTaxLines(Carbon $startDate, Carbon $endDate): array
    {
        $invoices = Invoice::query()
            ->with('user')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_taxable', true)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->orderBy('date')
            ->get();

        $lines = [];
        $totalDpp = 0.0;
        $totalPpn = 0.0;

        foreach ($invoices as $inv) {
            $dpp = (float) ($inv->tax_base ?: $inv->subtotal);
            $ppn = (float) $inv->ppn_amount;

            $lines[] = [
                'date'               => optional($inv->date)->toDateString(),
                'invoice_number'     => $inv->number,
                'tax_invoice_number' => $inv->tax_invoice_number,
                'customer'           => $inv->user?->name ?? '—',
                'npwp'               => $inv->user?->npwp,
                'dpp'                => round($dpp, 2),
                'ppn'                => round($ppn, 2),
            ];

            $totalDpp += $dpp;
            $totalPpn += $ppn;
        }

        return [
            'lines'     => $lines,
            'total_dpp' => round($totalDpp, 2),
            'total_ppn' => round($totalPpn, 2),
        ];
    }

    /**
     * PPh 23 withheld by customers in a period (our prepaid-tax credit). Basis for a
     * rekap kredit pajak / reconciling the bukti potong received from customers.
     *
     * @return array{lines: array, total_dpp: float, total_pph23: float}
     */
    public static function withholdingLines(Carbon $startDate, Carbon $endDate): array
    {
        $invoices = Invoice::query()
            ->with('user')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('pph23_amount', '>', 0)
            ->orderBy('date')
            ->get();

        $lines = [];
        $totalDpp = 0.0;
        $totalPph = 0.0;

        foreach ($invoices as $inv) {
            $dpp = (float) ($inv->tax_base ?: $inv->subtotal);
            $pph = (float) $inv->pph23_amount;

            $lines[] = [
                'date'            => optional($inv->date)->toDateString(),
                'invoice_number'  => $inv->number,
                'bukti_potong'    => $inv->pph23_bukti_potong_number,
                'customer'        => $inv->user?->name ?? '—',
                'npwp'            => $inv->user?->npwp,
                'dpp'             => round($dpp, 2),
                'pph23'           => round($pph, 2),
            ];

            $totalDpp += $dpp;
            $totalPph += $pph;
        }

        return [
            'lines'        => $lines,
            'total_dpp'    => round($totalDpp, 2),
            'total_pph23'  => round($totalPph, 2),
        ];
    }
}
