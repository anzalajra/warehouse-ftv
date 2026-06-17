<?php

namespace App\Filament\Actions;

use App\Models\Account;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Invoice;
use App\Services\JournalService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for recording an invoice payment.
 *
 * Records a FinanceTransaction (income) linked to the Invoice, re-runs
 * Invoice::recalculate() (which derives paid_amount + status), and posts the
 * matching journal entry (debit the finance account's linked ledger account,
 * credit 2-1300 Pendapatan Diterima Dimuka).
 *
 * Reused by the Invoice table, the Invoice View page, and the Finance
 * Accounts Receivable page so the journal-entry step can never be skipped
 * (the AR page previously omitted it — that bug is fixed by sharing this).
 */
class RecordPaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'record_payment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Record Payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->status !== Invoice::STATUS_PAID
                && $record->status !== 'cancelled'
                && $record->balance > 0)
            ->form(fn (Invoice $record): array => [
                Select::make('finance_account_id')
                    ->label('Deposit To Account')
                    ->options(FinanceAccount::where('is_active', true)->pluck('name', 'id'))
                    ->required(),
                TextInput::make('amount')
                    ->label('Amount')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->default($record->balance)
                    ->maxValue($record->balance),
                DatePicker::make('date')
                    ->label('Payment Date')
                    ->default(now())
                    ->required(),
                Select::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'Cash' => 'Cash',
                        'Transfer' => 'Bank Transfer',
                        'QRIS' => 'QRIS',
                        'Credit Card' => 'Credit Card',
                    ])
                    ->required(),
                Textarea::make('notes')
                    ->label('Notes'),
            ])
            ->action(function (Invoice $record, array $data): void {
                $transaction = new FinanceTransaction([
                    'finance_account_id' => $data['finance_account_id'],
                    'user_id' => Auth::id(),
                    'type' => FinanceTransaction::TYPE_INCOME,
                    'amount' => $data['amount'],
                    'date' => $data['date'],
                    'category' => 'Invoice Payment',
                    'description' => 'Payment for Invoice #' . $record->number,
                    'payment_method' => $data['payment_method'],
                    'notes' => $data['notes'] ?? null,
                ]);
                $transaction->reference()->associate($record);
                $transaction->save();

                // Recalculate invoice status (paid_amount + paid/partial/waiting).
                $record->recalculate();

                // Journal Entry: Debit Kas/Bank (finance account's linked ledger),
                // Credit 2-1300 Pendapatan Diterima Dimuka.
                $financeAccount = FinanceAccount::find($data['finance_account_id']);
                $debitAccountId = $financeAccount?->linked_account_id;
                $creditAccountId = Account::where('code', '2-1300')->first()?->id;

                if ($debitAccountId && $creditAccountId) {
                    JournalService::createEntry(
                        $record,
                        'Payment for Invoice #' . $record->number,
                        [
                            ['account_id' => $debitAccountId, 'debit' => $data['amount'], 'credit' => 0],
                            ['account_id' => $creditAccountId, 'debit' => 0, 'credit' => $data['amount']],
                        ],
                        $data['date']
                    );
                }

                Notification::make()
                    ->title('Payment Recorded')
                    ->success()
                    ->send();
            });
    }
}
