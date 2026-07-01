<?php

namespace App\Observers;

use App\Models\FinanceTransaction;
use App\Models\Account;
use App\Services\JournalService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FinanceTransactionObserver
{
    /**
     * Categories whose GL journal is posted EXPLICITLY by RentalAccountingService
     * (Dr Kas / Cr Piutang or Cr Uang Muka). The category-mapping auto-journal must
     * skip them, otherwise seeding a CategoryMapping for these would double-post.
     */
    protected const EXPLICITLY_POSTED_CATEGORIES = [
        'Down Payment',
        'Rental Payment',
        'Invoice Payment',
        'Security Deposit In',
        'Security Deposit Refund',
        'Security Deposit Deduction',
        'Bill Payment',
    ];

    /**
     * Handle the FinanceTransaction "created" event.
     */
    public function created(FinanceTransaction $transaction): void
    {
        if (in_array($transaction->category, self::EXPLICITLY_POSTED_CATEGORIES, true)) {
            return;
        }

        JournalService::syncFromTransaction($transaction);
    }

    /**
     * Handle the FinanceTransaction "updated" event.
     */
    public function updated(FinanceTransaction $financeTransaction): void
    {
        // TODO: Update journal entry? Or void and recreate?
        // For now, complex to handle updates to amounts/accounts.
    }

    /**
     * Handle the FinanceTransaction "deleted" event.
     *
     * Reverse the transaction's own GL journal entry (posted by syncFromTransaction
     * for ad-hoc income/expense) so the ledger stays in sync instead of orphaning it.
     * Payment/deposit transactions journal against their Invoice/Rental, not against
     * themselves, so they are untouched here (managed via those flows).
     */
    public function deleted(FinanceTransaction $financeTransaction): void
    {
        $entry = $financeTransaction->journalEntry;

        if ($entry && ! $entry->isReversed() && ! $entry->isReversal()) {
            JournalService::reverseEntry($entry, 'Transaksi #'.$financeTransaction->id.' dihapus');
        }
    }
}
