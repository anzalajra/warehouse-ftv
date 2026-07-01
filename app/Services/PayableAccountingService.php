<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bill;
use App\Models\FinanceAccount;
use App\Models\JournalEntry;
use App\Models\Setting;

/**
 * Accounts-Payable double entry for vendor Bills, the counterpart to
 * RentalAccountingService on the AR side. Without this, the GL-based Balance Sheet
 * (LedgerReportService) shows zero Hutang Usaha even when open bills exist.
 *
 *   Bill issued  Dr Beban[kategori]      / Cr Hutang Usaha (2-1100)   — accrue the payable
 *   Bill paid    Dr Hutang Usaha (2-1100) / Cr Kas                    — settle it
 *
 * All methods no-op unless finance is in `advanced` mode.
 */
class PayableAccountingService
{
    public const ACC_PAYABLE       = '2-1100'; // Hutang Usaha
    public const ACC_CASH_DEFAULT  = '1-1100';
    public const ACC_EXPENSE_DEFAULT = '5-2500'; // Beban Perlengkapan (fallback)

    /** Bill category -> default expense account code (best effort; reclassify in GL if needed). */
    protected const CATEGORY_ACCOUNTS = [
        'Utilities' => '5-2300', // Beban Listrik, Air, Internet
        'Inventory' => '5-2500', // Beban Perlengkapan
        'Service'   => '5-1100', // Beban Perawatan Aset
        'Rent'      => '5-2200', // Beban Sewa Kantor
        'Other'     => '5-2500',
    ];

    protected static function isAdvanced(): bool
    {
        return Setting::get('finance_mode', 'simple') === 'advanced';
    }

    protected static function acct(string $code): ?int
    {
        return Account::where('code', $code)->value('id');
    }

    protected static function expenseAccountId(?string $category): ?int
    {
        $code = self::CATEGORY_ACCOUNTS[$category] ?? Setting::get('ap_default_expense_account', self::ACC_EXPENSE_DEFAULT);

        return self::acct($code) ?: self::acct(self::ACC_EXPENSE_DEFAULT);
    }

    /**
     * Accrue the expense + payable when a bill is recorded. Idempotent per bill.
     */
    public static function postBillIssued(Bill $bill): void
    {
        if (! self::isAdvanced() || (float) $bill->amount <= 0) {
            return;
        }

        $already = JournalEntry::where('reference_type', Bill::class)
            ->where('reference_id', $bill->id)
            ->where('description', 'like', 'Tagihan vendor%')
            ->exists();
        if ($already) {
            return;
        }

        $expense = self::expenseAccountId($bill->category);
        $payable = self::acct(self::ACC_PAYABLE);
        if (! $expense || ! $payable) {
            return;
        }

        $amount = (float) $bill->amount;

        JournalService::createEntry($bill, 'Tagihan vendor '.($bill->bill_number ?: $bill->id).' — '.$bill->vendor_name, [
            ['account_id' => $expense, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $payable, 'debit' => 0,       'credit' => $amount],
        ], $bill->bill_date);
    }

    /**
     * Settle (part of) a payable in cash: Dr Hutang Usaha (2-1100) / Cr Kas.
     */
    public static function postBillPayment(Bill $bill, int $financeAccountId, float $amount, $date = null): void
    {
        if (! self::isAdvanced() || $amount <= 0) {
            return;
        }

        $payable = self::acct(self::ACC_PAYABLE);
        $cash = FinanceAccount::whereKey($financeAccountId)->value('linked_account_id') ?: self::acct(self::ACC_CASH_DEFAULT);
        if (! $payable || ! $cash) {
            return;
        }

        JournalService::createEntry($bill, 'Pembayaran tagihan '.($bill->bill_number ?: $bill->id), [
            ['account_id' => $payable, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $cash,    'debit' => 0,       'credit' => $amount],
        ], $date);
    }
}
