<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\CategoryMapping;
use App\Models\JournalEntry;
use App\Models\JournalEntryItem;
use App\Models\FinanceTransaction;
use App\Models\Account;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalService
{
    /**
     * Sync a FinanceTransaction to Journal Entry.
     * Creates a Journal Entry if it doesn't exist.
     */
    public static function syncFromTransaction(FinanceTransaction $transaction, array $manualMappings = []): void
    {
        // Check if Journal Entry already exists
        if ($transaction->journalEntry()->exists()) {
            return;
        }

        // 1. Get the Cash/Bank Account (from FinanceAccount)
        $financeAccount = $transaction->account;

        if (!$financeAccount) {
            Log::warning("FinanceTransaction #{$transaction->id} has no FinanceAccount.");
            return;
        }

        if (!$financeAccount->linked_account_id) {
             Log::warning("FinanceTransaction #{$transaction->id}: FinanceAccount #{$financeAccount->id} is not linked to a GL Account.");
             return;
        }
        
        $cashAccountId = $financeAccount->linked_account_id;

        // 2. Get the Contra Account (Revenue/Expense/Liability)
        $contraAccountId = self::resolveContraAccount($transaction, $manualMappings);

        if (!$contraAccountId) {
            Log::warning("FinanceTransaction #{$transaction->id}: Could not determine contra GL account for category '{$transaction->category}'.");
            return;
        }

        // 3. Create Journal Items — the GL is kept in BASE currency, so a foreign-
        // currency transaction posts its base equivalent (amount × exchange_rate).
        $items = [];
        $amount = $transaction->baseAmount();
        $description = $transaction->description ?: "Transaction #{$transaction->type} - {$transaction->category}";

        if ($transaction->type === FinanceTransaction::TYPE_INCOME || $transaction->type === FinanceTransaction::TYPE_DEPOSIT_IN) {
            // Debit Cash, Credit Income/Liability
            $items[] = ['account_id' => $cashAccountId, 'debit' => $amount, 'credit' => 0];
            $items[] = ['account_id' => $contraAccountId, 'debit' => 0, 'credit' => $amount];
        } elseif ($transaction->type === FinanceTransaction::TYPE_EXPENSE || $transaction->type === FinanceTransaction::TYPE_DEPOSIT_OUT) {
            // Debit Expense/Liability, Credit Cash
            $items[] = ['account_id' => $contraAccountId, 'debit' => $amount, 'credit' => 0];
            $items[] = ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => $amount];
        } elseif ($transaction->type === FinanceTransaction::TYPE_TRANSFER) {
             // For transfer, if we treat it as mapped to another account:
             $items[] = ['account_id' => $contraAccountId, 'debit' => $amount, 'credit' => 0];
             $items[] = ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => $amount];
        }

        if (!empty($items)) {
            self::createEntry($transaction, $description, $items, $transaction->date);
        }
    }

    public static function syncFromInvoice(\App\Models\Invoice $invoice): void
    {
        if ($invoice->journalEntry()->exists()) {
            return;
        }
        
        // Ensure invoice is sent/paid/partial
        if (!in_array($invoice->status, ['sent', 'paid', 'partial'])) {
            return;
        }

        $arAccountId = Setting::get('account_receivable_id');
        $revenueAccountId = Setting::get('rental_revenue_id');
        $taxPayableAccountId = Setting::get('tax_payable_account_id'); // Optional

        if (!$arAccountId || !$revenueAccountId) {
            Log::warning("Invoice #{$invoice->id}: Missing Default AR or Revenue Account in Settings.");
            return;
        }

        $items = [];
        
        // Debit AR (Total)
        $items[] = ['account_id' => $arAccountId, 'debit' => $invoice->total, 'credit' => 0];
        
        // Credit Revenue (Subtotal)
        // Assuming subtotal is net revenue. 
        $revenueAmount = $invoice->subtotal; 
        $items[] = ['account_id' => $revenueAccountId, 'debit' => 0, 'credit' => $revenueAmount];
        
        // Credit Tax Payable
        if ($invoice->tax > 0) {
            if ($taxPayableAccountId) {
                $items[] = ['account_id' => $taxPayableAccountId, 'debit' => 0, 'credit' => $invoice->tax];
            } else {
                // Add to revenue if no tax account
                $items[1]['credit'] += $invoice->tax;
            }
        }

        self::createEntry($invoice, "Invoice #{$invoice->number}", $items, $invoice->date);
    }

    /**
     * Analyze unsynced transactions and return categories that need manual mapping.
     * Returns array of categories.
     */
    public static function getUnresolvedCategories(): array
    {
        // Get categories from FinanceTransactions that don't have a JournalEntry
        $unsyncedCategories = FinanceTransaction::doesntHave('journalEntry')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->toArray();

        $unresolved = [];
        foreach ($unsyncedCategories as $category) {
            if (!self::isCategoryAutomaticallyResolvable($category)) {
                $unresolved[] = $category;
            }
        }

        return $unresolved;
    }

    /**
     * Check if a category can be automatically resolved to an account.
     */
    protected static function isCategoryAutomaticallyResolvable(?string $category): bool
    {
        if (empty($category)) return false;
        
        // Check Category Mapping
        if (CategoryMapping::where('category', $category)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Resolve the contra GL account based on transaction category, type, and mappings.
     */
    protected static function resolveContraAccount(FinanceTransaction $transaction, array $manualMappings = []): ?int
    {
        $category = $transaction->category ?? '';
        
        // 1. Check Manual Mappings
        if (isset($manualMappings[$category])) {
            // Persist mapping
            CategoryMapping::updateOrCreate(
                ['category' => $category],
                ['account_id' => $manualMappings[$category]]
            );
            return $manualMappings[$category];
        }

        // 2. Check Database Mappings
        $mapping = CategoryMapping::where('category', $category)->first();
        if ($mapping) {
            return $mapping->account_id;
        }

        // 3. Fall back to a type-appropriate default account so ad-hoc income/expense
        // still posts to the ledger (instead of silently skipping). Rental/deposit/bill
        // transactions never reach here — they are on the observer denylist and post
        // their GL entries explicitly.
        $defaults = [
            FinanceTransaction::TYPE_INCOME      => '4-1300', // Pendapatan Jasa Lainnya
            FinanceTransaction::TYPE_EXPENSE     => '5-2500', // Beban Perlengkapan
            FinanceTransaction::TYPE_DEPOSIT_IN  => '2-1200', // Uang Jaminan
            FinanceTransaction::TYPE_DEPOSIT_OUT => '2-1200',
            FinanceTransaction::TYPE_TRANSFER    => '1-1100', // Kas
        ];

        if (isset($defaults[$transaction->type])) {
            return Account::where('code', $defaults[$transaction->type])->value('id');
        }

        return null;
    }

    /**
     * Get the account ID for a specific event and role from mappings.
     */
    public static function getAccount(string $event, string $role): ?int
    {
        return AccountMapping::where('event', $event)
            ->where('role', $role)
            ->value('account_id');
    }

    /**
     * Create a journal entry with multiple items.
     * items format: [['account_id' => 1, 'debit' => 100, 'credit' => 0], ...]
     */
    public static function createEntry(Model $reference, string $description, array $items, ?string $date = null): ?JournalEntry
    {
        if (empty($items)) {
            return null;
        }

        // Enforce the fundamental double-entry rule: debit MUST equal credit.
        // An unbalanced entry corrupts the ledger, so refuse it (rolls back the
        // surrounding transaction) instead of silently saving a broken row.
        $totalDebit = round((float) collect($items)->sum('debit'), 2);
        $totalCredit = round((float) collect($items)->sum('credit'), 2);

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \RuntimeException(
                "Refusing to post unbalanced journal entry (Debit: {$totalDebit}, Credit: {$totalCredit}) — {$description}"
            );
        }

        // Refuse to post into a closed accounting period (tutup buku).
        self::assertPeriodOpen($date);

        return DB::transaction(function () use ($reference, $description, $items, $date) {
            $entry = JournalEntry::create([
                'reference_number' => self::nextReferenceNumber($date),
                'date' => $date ?? now(),
                'description' => $description,
                'reference_type' => get_class($reference),
                'reference_id' => $reference->id,
            ]);

            foreach ($items as $item) {
                if (($item['debit'] ?? 0) == 0 && ($item['credit'] ?? 0) == 0) {
                    continue;
                }
                
                JournalEntryItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $item['account_id'],
                    'debit' => $item['debit'] ?? 0,
                    'credit' => $item['credit'] ?? 0,
                ]);
            }
            
            // Recalculate balances
            foreach ($items as $item) {
                if ($item['account_id']) {
                     $account = Account::find($item['account_id']);
                     $account?->recalculateBalance();
                }
            }

            return $entry;
        });
    }

    /**
     * The date up to and including which the books are closed. Postings dated on or
     * before it are rejected. Stored in Setting `finance_locked_until` (YYYY-MM-DD).
     */
    public static function periodLockDate(): ?\Carbon\Carbon
    {
        $raw = Setting::get('finance_locked_until');
        if (empty($raw)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($raw)->endOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Guard against posting into a closed period. Throws when $date <= lock date.
     */
    public static function assertPeriodOpen(?string $date = null): void
    {
        $lock = self::periodLockDate();
        if (! $lock) {
            return;
        }

        $entryDate = $date ? \Carbon\Carbon::parse($date) : now();
        if ($entryDate->lte($lock)) {
            throw new \RuntimeException(
                'Periode akuntansi sudah ditutup sampai '.$lock->toDateString().'. Buka periode (Settings → Finance) untuk memposting tanggal ini.'
            );
        }
    }

    /**
     * Reverse a posted journal entry by creating a mirror entry (debit/credit swapped)
     * in an OPEN period, dated today (or $date). Marks the original as reversed.
     * The correct way to undo a posting without editing/deleting history.
     */
    public static function reverseEntry(JournalEntry $original, ?string $reason = null, ?string $date = null): ?JournalEntry
    {
        if ($original->isReversal()) {
            throw new \RuntimeException('Tidak bisa membalik entri pembalik.');
        }
        if ($original->isReversed()) {
            throw new \RuntimeException('Entri jurnal ini sudah pernah dibalik.');
        }

        $original->loadMissing('items');
        if ($original->items->isEmpty()) {
            return null;
        }

        $date = $date ?? now()->toDateString();
        self::assertPeriodOpen($date);

        return DB::transaction(function () use ($original, $reason, $date) {
            $entry = JournalEntry::create([
                'reference_number' => self::nextReferenceNumber($date),
                'date' => $date,
                'description' => 'Pembalik: '.$original->description.($reason ? ' — '.$reason : ''),
                'reference_type' => $original->reference_type,
                'reference_id' => $original->reference_id,
                'reversal_of_id' => $original->id,
            ]);

            foreach ($original->items as $item) {
                JournalEntryItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $item->account_id,
                    'debit' => $item->credit, // swapped
                    'credit' => $item->debit,
                ]);
                $item->account?->recalculateBalance();
            }

            $original->update(['reversed_at' => now()]);

            return $entry;
        });
    }

    /**
     * Sequential, collision-free journal number: JRN-YYYYMM-##### (5-digit running
     * sequence per month). Replaces the old rand()-based suffix which was neither
     * sequential nor unique — a requirement for auditable Indonesian bookkeeping.
     */
    protected static function nextReferenceNumber(?string $date = null): string
    {
        $prefix = 'JRN-' . ($date ? date('Ym', strtotime($date)) : date('Ym')) . '-';

        $last = JournalEntry::where('reference_number', 'like', $prefix . '%')
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $sequence = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Record a simple transaction where one account is debited and another credited with the same amount.
     */
    public static function recordSimpleTransaction(string $event, Model $reference, float $amount, ?string $description = null): ?JournalEntry
    {
        $debitAccountId = self::getAccount($event, 'debit');
        $creditAccountId = self::getAccount($event, 'credit');

        if (!$debitAccountId || !$creditAccountId) {
            return null;
        }

        $items = [
            [
                'account_id' => $debitAccountId,
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $amount,
            ],
        ];

        return self::createEntry($reference, $description ?? $event, $items);
    }
}
