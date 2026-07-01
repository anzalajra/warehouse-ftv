<?php

namespace App\Filament\Actions;

use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Rental;
use App\Services\RentalAccountingService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Single source of truth for refunding / forfeiting a held security deposit.
 *
 * Refund portion  -> deposit_out FinanceTransaction + Dr Uang Jaminan (2-1200) / Cr Kas.
 * Deduction part  -> income FinanceTransaction     + Dr Uang Jaminan (2-1200) / Cr Pendapatan Denda (4-1200).
 * Shared by RentalsTable and the Customer Deposits page.
 */
class RefundDepositAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'refund_deposit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Refund Deposit')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (Rental $record): bool => $record->security_deposit_status === 'held')
            ->form([
                Select::make('finance_account_id')
                    ->label('Account')
                    ->options(FinanceAccount::where('is_active', true)->pluck('name', 'id'))
                    ->required(),
                TextInput::make('amount')
                    ->label('Refund Amount')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(fn (Rental $record) => $record->security_deposit_amount > 0 ? $record->security_deposit_amount : $record->deposit)
                    ->required(),
                TextInput::make('deduction')
                    ->label('Deduction (Damage/Late)')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(0),
                DatePicker::make('transaction_date')
                    ->default(now())
                    ->required(),
                Textarea::make('notes')
                    ->label('Refund Notes'),
            ])
            ->action(function (Rental $record, array $data): void {
                $deduction = (float) ($data['deduction'] ?? 0);
                $refundAmount = (float) $data['amount'];
                $transactionDate = $data['transaction_date'];

                // Outgoing refund to the customer.
                if ($refundAmount > 0) {
                    $out = new FinanceTransaction([
                        'finance_account_id' => $data['finance_account_id'],
                        'type' => FinanceTransaction::TYPE_DEPOSIT_OUT,
                        'amount' => $refundAmount,
                        'description' => 'Deposit Refund: ' . $record->rental_code,
                        'category' => 'Security Deposit Refund',
                        'date' => $transactionDate,
                    ]);
                    $out->reference()->associate($record);
                    $out->save();

                    RentalAccountingService::postDepositRefund(
                        $record,
                        (int) $data['finance_account_id'],
                        $refundAmount,
                        $transactionDate
                    );
                }

                // Deduction kept as penalty income.
                if ($deduction > 0) {
                    $income = new FinanceTransaction([
                        'finance_account_id' => $data['finance_account_id'],
                        'type' => FinanceTransaction::TYPE_INCOME,
                        'amount' => $deduction,
                        'description' => 'Deposit Deduction: ' . $record->rental_code,
                        'category' => 'Security Deposit Deduction',
                        'date' => $transactionDate,
                    ]);
                    $income->reference()->associate($record);
                    $income->save();

                    RentalAccountingService::postDepositForfeit($record, $deduction, $transactionDate);
                }

                $record->update([
                    'security_deposit_status' => $deduction > 0 ? 'partial_refunded' : 'refunded',
                    'security_deposit_amount' => 0,
                ]);

                Notification::make()->title('Deposit Refunded')->success()->send();
            });
    }
}
