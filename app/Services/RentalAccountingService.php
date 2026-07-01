<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FinanceAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Rental;
use App\Models\Setting;

/**
 * Single, standard-aware source of truth for every rental-cycle GL posting.
 *
 * Replaces the scattered `JournalService::recordSimpleTransaction('RENTAL_*', …)`
 * calls that produced an inconsistent ledger:
 *   - revenue was recognized TWICE (at invoice issue AND at completion),
 *   - payments credited Unearned (2-1300) instead of clearing Receivable (1-1200),
 *   - PPN was never split out to Tax Payable (2-1400),
 *   - late fees never hit Other Income / Denda (4-1200).
 *
 * The correct flow (verified balanced for both standards):
 *
 *   Advance (DP before invoice)   Dr Kas          / Cr Uang Muka (2-1300)
 *   Invoice issued                Dr Piutang      / Cr Revenue-or-Deferred + PPN + Denda
 *   Reclassify advance            Dr 2-1300       / Cr Piutang            (settle prepaid part)
 *   Payment (AR collection)       Dr Kas          / Cr Piutang
 *   Revenue recognition (IFRS)    Dr 2-1300       / Cr Pendapatan Sewa    (once, at completion)
 *   Late fee                      Dr Piutang      / Cr Pendapatan Denda (4-1200)
 *
 * Standard switch (Setting `accounting_standard`):
 *   - `sak`  — point-in-time: rental revenue recognized when the invoice is issued
 *              (Cr 4-1100 directly). Completion posts no revenue journal.
 *   - `ifrs` — over-time (IFRS 15 / PSAK 72 / ASC 606): the invoice defers revenue
 *              to 2-1300; it is recognized to 4-1100 once at completion.
 *
 * All methods no-op unless finance is in `advanced` (double-entry) mode.
 */
class RentalAccountingService
{
    // Chart-of-accounts codes (see ChartOfAccountsSeeder).
    public const ACC_CASH_DEFAULT     = '1-1100'; // Kas dan Setara Kas (fallback)
    public const ACC_RECEIVABLE       = '1-1200'; // Piutang Usaha
    public const ACC_DEPOSIT_LIABILITY = '2-1200'; // Uang Jaminan Pelanggan (Deposit)
    public const ACC_DEFERRED         = '2-1300'; // Pendapatan Diterima Dimuka / Uang Muka
    public const ACC_TAX_PAYABLE      = '2-1400'; // Hutang Pajak (PPN Keluaran)
    public const ACC_RENTAL_REVENUE   = '4-1100'; // Pendapatan Sewa
    public const ACC_PENALTY_INCOME   = '4-1200'; // Pendapatan Denda
    public const ACC_PPH23_PREPAID    = '1-1500'; // PPh 23 Dibayar Dimuka (tax credit)

    public const STANDARD_SAK  = 'sak';
    public const STANDARD_IFRS = 'ifrs';

    public static function standardOptions(): array
    {
        return [
            self::STANDARD_SAK  => 'SAK (Indonesia — akui pendapatan saat invoice)',
            self::STANDARD_IFRS => 'IFRS / ASC 606 / PSAK 72 (akui bertahap saat sewa selesai)',
        ];
    }

    public static function standard(): string
    {
        return Setting::get('accounting_standard', self::STANDARD_SAK) === self::STANDARD_IFRS
            ? self::STANDARD_IFRS
            : self::STANDARD_SAK;
    }

    protected static function isAdvanced(): bool
    {
        return Setting::get('finance_mode', 'simple') === 'advanced';
    }

    protected static function acct(string $code): ?int
    {
        return Account::where('code', $code)->value('id');
    }

    /**
     * The revenue components of an invoice, EXCLUDING any security deposit
     * (deposit is a liability tracked via its own deposit_in/out journals).
     *
     * @return array{billable: float, net_revenue: float, ppn: float, late_fee: float}
     */
    protected static function invoiceComponents(Invoice $invoice): array
    {
        $deposit  = (float) $invoice->rentals->sum('security_deposit_amount');
        $ppn      = (float) ($invoice->ppn_amount ?? $invoice->tax);
        $lateFee  = (float) $invoice->late_fee;
        $billable = (float) $invoice->total - $deposit;
        $revenue  = max(0.0, $billable - $ppn - $lateFee);

        return [
            'billable'    => round($billable, 2),
            'net_revenue' => round($revenue, 2),
            'ppn'         => round($ppn, 2),
            'late_fee'    => round($lateFee, 2),
        ];
    }

    /** Same decomposition at the rental level (for completion-time recognition). */
    protected static function rentalNetRevenue(Rental $rental): float
    {
        $deposit  = (float) $rental->security_deposit_amount;
        $ppn      = (float) ($rental->ppn_amount ?? 0);
        $lateFee  = (float) ($rental->late_fee ?? 0);
        $billable = (float) $rental->total - $deposit;

        return round(max(0.0, $billable - $ppn - $lateFee), 2);
    }

    /**
     * A customer down payment received BEFORE any invoice exists = a liability
     * (advance from customer): Dr Kas / Cr Uang Muka (2-1300).
     */
    public static function postAdvance(Rental $rental, int $financeAccountId, float $amount, $date = null): void
    {
        if (! self::isAdvanced() || $amount <= 0) {
            return;
        }

        $cash = self::cashAccountId($financeAccountId);
        $deferred = self::acct(self::ACC_DEFERRED);
        if (! $cash || ! $deferred) {
            return;
        }

        JournalService::createEntry($rental, 'Uang muka sewa '.$rental->rental_code, [
            ['account_id' => $cash,     'debit' => $amount, 'credit' => 0],
            ['account_id' => $deferred, 'debit' => 0,       'credit' => $amount],
        ], $date);
    }

    /**
     * Recognize the receivable when an invoice is issued. Idempotent per invoice.
     *   Dr Piutang (billable)  / Cr Revenue-or-Deferred (net) + PPN (2-1400) + Denda (4-1200)
     */
    public static function postInvoiceIssued(Invoice $invoice): void
    {
        if (! self::isAdvanced()) {
            return;
        }

        // Idempotency: never post the issuance entry twice (payments also reference
        // the invoice, so guard on the issuance marker rather than journalEntry()->exists()).
        $already = JournalEntry::where('reference_type', Invoice::class)
            ->where('reference_id', $invoice->id)
            ->where('description', 'like', 'Penerbitan Invoice%')
            ->exists();
        if ($already) {
            return;
        }

        $invoice->loadMissing('rentals');
        $c = self::invoiceComponents($invoice);

        if ($c['billable'] <= 0) {
            return;
        }

        $receivable = self::acct(self::ACC_RECEIVABLE);
        $revenueAcc = self::standard() === self::STANDARD_IFRS
            ? self::acct(self::ACC_DEFERRED)
            : self::acct(self::ACC_RENTAL_REVENUE);
        if (! $receivable || ! $revenueAcc) {
            return;
        }

        $items = [
            ['account_id' => $receivable, 'debit' => $c['billable'], 'credit' => 0],
        ];
        if ($c['net_revenue'] > 0) {
            $items[] = ['account_id' => $revenueAcc, 'debit' => 0, 'credit' => $c['net_revenue']];
        }
        if ($c['ppn'] > 0 && ($tax = self::acct(self::ACC_TAX_PAYABLE))) {
            $items[] = ['account_id' => $tax, 'debit' => 0, 'credit' => $c['ppn']];
        }
        if ($c['late_fee'] > 0 && ($penalty = self::acct(self::ACC_PENALTY_INCOME))) {
            $items[] = ['account_id' => $penalty, 'debit' => 0, 'credit' => $c['late_fee']];
        }

        JournalService::createEntry(
            $invoice,
            'Penerbitan Invoice #'.$invoice->number,
            $items,
            $invoice->date
        );
    }

    /**
     * Move a previously-received advance against the receivable now that the
     * invoice exists: Dr Uang Muka (2-1300) / Cr Piutang (1-1200).
     */
    public static function reclassifyAdvanceToReceivable(Invoice $invoice, float $amount): void
    {
        if (! self::isAdvanced() || $amount <= 0) {
            return;
        }

        $deferred   = self::acct(self::ACC_DEFERRED);
        $receivable = self::acct(self::ACC_RECEIVABLE);
        if (! $deferred || ! $receivable) {
            return;
        }

        JournalService::createEntry($invoice, 'Reklasifikasi uang muka Invoice #'.$invoice->number, [
            ['account_id' => $deferred,   'debit' => $amount, 'credit' => 0],
            ['account_id' => $receivable, 'debit' => 0,       'credit' => $amount],
        ], $invoice->date);
    }

    /**
     * Collect payment against an invoice: Dr Kas / Cr Piutang (settles AR).
     * Same for both standards.
     *
     * When the customer withholds PPh 23 ($withholding > 0), the receivable is
     * settled by cash PLUS the withheld amount recorded as a prepaid-tax credit:
     *   Dr Kas ($amount) + Dr PPh 23 Dibayar Dimuka (1-1500, $withholding) / Cr Piutang.
     */
    public static function postPayment(Invoice $invoice, int $financeAccountId, float $amount, $date = null, float $withholding = 0): void
    {
        if (! self::isAdvanced() || ($amount <= 0 && $withholding <= 0)) {
            return;
        }

        $cash = self::cashAccountId($financeAccountId);
        $receivable = self::acct(self::ACC_RECEIVABLE);
        if (! $cash || ! $receivable) {
            return;
        }

        $items = [];
        if ($amount > 0) {
            $items[] = ['account_id' => $cash, 'debit' => $amount, 'credit' => 0];
        }
        if ($withholding > 0 && ($pph23 = self::acct(self::ACC_PPH23_PREPAID))) {
            $items[] = ['account_id' => $pph23, 'debit' => $withholding, 'credit' => 0];
        }
        $items[] = ['account_id' => $receivable, 'debit' => 0, 'credit' => $amount + $withholding];

        JournalService::createEntry($invoice, 'Pembayaran Invoice #'.$invoice->number, $items, $date);
    }

    /**
     * Recognize rental revenue at completion. IFRS only (SAK recognizes at invoice).
     * Idempotent via rentals.revenue_recognized_at.
     *   Dr Pendapatan Diterima Dimuka (2-1300) / Cr Pendapatan Sewa (4-1100)
     */
    public static function postRevenueRecognition(Rental $rental): void
    {
        if ($rental->revenue_recognized_at) {
            return;
        }

        // Mark as handled regardless of standard so re-runs stay idempotent.
        $mark = fn () => $rental->forceFill(['revenue_recognized_at' => now()])->saveQuietly();

        if (! self::isAdvanced() || self::standard() !== self::STANDARD_IFRS) {
            $mark();
            return;
        }

        $net = self::rentalNetRevenue($rental);
        $deferred = self::acct(self::ACC_DEFERRED);
        $revenue  = self::acct(self::ACC_RENTAL_REVENUE);

        if ($net > 0 && $deferred && $revenue) {
            JournalService::createEntry($rental, 'Pengakuan pendapatan sewa '.$rental->rental_code, [
                ['account_id' => $deferred, 'debit' => $net, 'credit' => 0],
                ['account_id' => $revenue,  'debit' => 0,    'credit' => $net],
            ]);
        }

        $mark();
    }

    /**
     * Recognize a late fee as penalty income: Dr Piutang / Cr Pendapatan Denda (4-1200).
     * The receivable side mirrors the fee that Invoice::recalculate() adds to the total.
     */
    public static function postLateFee(Rental $rental, float $amount, $date = null): void
    {
        if (! self::isAdvanced() || $amount <= 0) {
            return;
        }

        $receivable = self::acct(self::ACC_RECEIVABLE);
        $penalty    = self::acct(self::ACC_PENALTY_INCOME);
        if (! $receivable || ! $penalty) {
            return;
        }

        JournalService::createEntry($rental, 'Denda keterlambatan '.$rental->rental_code, [
            ['account_id' => $receivable, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $penalty,    'debit' => 0,       'credit' => $amount],
        ], $date);
    }

    /**
     * Security deposit received (a liability, NOT income): Dr Kas / Cr Uang Jaminan (2-1200).
     */
    public static function postDepositReceived(Rental $rental, int $financeAccountId, float $amount, $date = null): void
    {
        if (! self::isAdvanced() || $amount <= 0) {
            return;
        }

        $cash = self::cashAccountId($financeAccountId);
        $deposit = self::acct(self::ACC_DEPOSIT_LIABILITY);
        if (! $cash || ! $deposit) {
            return;
        }

        JournalService::createEntry($rental, 'Terima uang jaminan '.$rental->rental_code, [
            ['account_id' => $cash,    'debit' => $amount, 'credit' => 0],
            ['account_id' => $deposit, 'debit' => 0,       'credit' => $amount],
        ], $date);
    }

    /**
     * Security deposit refunded to the customer: Dr Uang Jaminan (2-1200) / Cr Kas.
     */
    public static function postDepositRefund(Rental $rental, int $financeAccountId, float $amount, $date = null): void
    {
        if (! self::isAdvanced() || $amount <= 0) {
            return;
        }

        $cash = self::cashAccountId($financeAccountId);
        $deposit = self::acct(self::ACC_DEPOSIT_LIABILITY);
        if (! $cash || ! $deposit) {
            return;
        }

        JournalService::createEntry($rental, 'Kembalikan uang jaminan '.$rental->rental_code, [
            ['account_id' => $deposit, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $cash,    'debit' => 0,       'credit' => $amount],
        ], $date);
    }

    /**
     * Deposit forfeited (kept as penalty income): Dr Uang Jaminan (2-1200) / Cr Pendapatan Denda (4-1200).
     */
    public static function postDepositForfeit(Rental $rental, float $amount, $date = null): void
    {
        if (! self::isAdvanced() || $amount <= 0) {
            return;
        }

        $deposit = self::acct(self::ACC_DEPOSIT_LIABILITY);
        $penalty = self::acct(self::ACC_PENALTY_INCOME);
        if (! $deposit || ! $penalty) {
            return;
        }

        JournalService::createEntry($rental, 'Pemotongan uang jaminan '.$rental->rental_code, [
            ['account_id' => $deposit, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $penalty, 'debit' => 0,       'credit' => $amount],
        ], $date);
    }

    /** Resolve a FinanceAccount (cash/bank) to its linked GL account id. */
    protected static function cashAccountId(int $financeAccountId): ?int
    {
        $linked = FinanceAccount::whereKey($financeAccountId)->value('linked_account_id');

        return $linked ?: self::acct(self::ACC_CASH_DEFAULT);
    }
}
