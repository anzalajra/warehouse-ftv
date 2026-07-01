<?php

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Models\Setting;
use App\Services\AgingReportService;
use App\Services\LedgerReportService;
use App\Services\TaxReportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Financial statements derived from the general ledger (posted journal entries),
 * as opposed to the operational aggregates in FinancialReports. Only meaningful in
 * Advanced (double-entry) mode, so it is hidden otherwise.
 */
class LedgerReports extends Page
{
    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Laporan GL';

    protected static ?string $title = 'Laporan Keuangan (General Ledger)';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.clusters.finance.pages.ledger-reports';

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public ?int $ledgerAccountId = null;

    public static function shouldRegisterNavigation(): bool
    {
        return Setting::get('finance_mode', 'simple') === 'advanced';
    }

    public function mount(): void
    {
        $this->startDate = $this->startDate ?: now()->startOfYear()->toDateString();
        $this->endDate = $this->endDate ?: now()->endOfMonth()->toDateString();
    }

    #[Computed]
    public function trialBalance(): array
    {
        // Conventional trial balance = cumulative balances as of the period-end date
        // (reconciles with the Balance Sheet, which is also as-of).
        return LedgerReportService::trialBalance(null, $this->endDate);
    }

    #[Computed]
    public function incomeStatement(): array
    {
        return LedgerReportService::incomeStatement($this->startDate, $this->endDate);
    }

    #[Computed]
    public function balanceSheet(): array
    {
        return LedgerReportService::balanceSheet($this->endDate);
    }

    #[Computed]
    public function arAging(): array
    {
        return AgingReportService::receivables($this->endDate);
    }

    #[Computed]
    public function apAging(): array
    {
        return AgingReportService::payables($this->endDate);
    }

    #[Computed]
    public function taxRecap(): array
    {
        return TaxReportService::outputTaxLines(
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate),
        );
    }

    #[Computed]
    public function pph23Recap(): array
    {
        return TaxReportService::withholdingLines(
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate),
        );
    }

    public function accountOptions(): array
    {
        return \App\Models\Account::query()
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn ($a) => [$a->id => "{$a->code} · {$a->name}"])
            ->toArray();
    }

    #[Computed]
    public function generalLedger(): ?array
    {
        if (! $this->ledgerAccountId) {
            return null;
        }

        return LedgerReportService::generalLedger(
            (int) $this->ledgerAccountId,
            $this->startDate,
            $this->endDate,
        );
    }

    /** Download the Balance Sheet + Income Statement as a PDF. */
    public function exportStatementsPdf(): StreamedResponse
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('filament.clusters.finance.pages.statements-pdf', [
            'incomeStatement' => LedgerReportService::incomeStatement($this->startDate, $this->endDate),
            'balanceSheet'    => LedgerReportService::balanceSheet($this->endDate),
            'startDate'       => $this->startDate,
            'endDate'         => $this->endDate,
            'siteName'        => Setting::get('site_name', 'Gearent'),
        ]);

        $filename = 'laporan-keuangan-' . $this->startDate . '-' . $this->endDate . '.pdf';

        return response()->streamDownload(fn () => print($pdf->output()), $filename, ['Content-Type' => 'application/pdf']);
    }

    /** Download the PPN Keluaran recap as CSV (Faktur Pajak / e-Faktur precursor). */
    public function exportTaxCsv(): StreamedResponse
    {
        $recap = TaxReportService::outputTaxLines(
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate),
        );

        $filename = 'ppn-keluaran-' . $this->startDate . '-' . $this->endDate . '.csv';

        return response()->streamDownload(function () use ($recap) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'No Invoice', 'No Faktur Pajak', 'Pelanggan', 'NPWP', 'DPP', 'PPN']);
            foreach ($recap['lines'] as $line) {
                fputcsv($out, [
                    $line['date'],
                    $line['invoice_number'],
                    $line['tax_invoice_number'],
                    $line['customer'],
                    $line['npwp'],
                    $line['dpp'],
                    $line['ppn'],
                ]);
            }
            fputcsv($out, ['', '', '', '', 'TOTAL', $recap['total_dpp'], $recap['total_ppn']]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
