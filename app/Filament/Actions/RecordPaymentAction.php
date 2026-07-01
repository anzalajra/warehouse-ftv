<?php

namespace App\Filament\Actions;

use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Invoice;
use App\Services\RentalAccountingService;
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
                \Filament\Forms\Components\Toggle::make('pph23_withheld')
                    ->label('Customer withholds PPh 23?')
                    ->helperText('Corporate customers may withhold PPh 23 (usually 2% of DPP). The withheld amount is recorded as a prepaid-tax credit.')
                    ->live(),
                TextInput::make('pph23_amount')
                    ->label('PPh 23 Withheld')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(fn (Invoice $record): float => round((float) ($record->tax_base ?: $record->subtotal) * 0.02, 2))
                    ->visible(fn ($get): bool => (bool) $get('pph23_withheld')),
                TextInput::make('pph23_bukti_potong_number')
                    ->label('No. Bukti Potong')
                    ->visible(fn ($get): bool => (bool) $get('pph23_withheld')),
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

                // PPh 23 withheld by the customer (recorded as a prepaid-tax credit).
                $withholding = ! empty($data['pph23_withheld']) ? (float) ($data['pph23_amount'] ?? 0) : 0.0;
                if ($withholding > 0) {
                    $record->pph23_withheld = true;
                    $record->pph23_amount = (float) $record->pph23_amount + $withholding;
                    $record->pph23_bukti_potong_number = $data['pph23_bukti_potong_number'] ?? $record->pph23_bukti_potong_number;
                    $record->save();
                }

                // Recalculate invoice status (paid_amount + PPh23 credit vs total).
                $record->recalculate();

                // Journal Entry: Dr Kas/Bank (+ Dr PPh23 Dibayar Dimuka if withheld)
                // / Cr Piutang Usaha (settles the receivable). Single source of truth.
                RentalAccountingService::postPayment(
                    $record,
                    (int) $data['finance_account_id'],
                    (float) $data['amount'],
                    $data['date'],
                    $withholding
                );

                Notification::make()
                    ->title('Payment Recorded')
                    ->success()
                    ->send();
            });
    }
}
