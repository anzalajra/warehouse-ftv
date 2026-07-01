<?php

namespace App\Filament\Actions;

use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Rental;
use App\Services\RentalAccountingService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Single source of truth for receiving a security deposit on a rental.
 *
 * Records a deposit_in FinanceTransaction (updates the cash account balance) AND
 * posts the GL journal Dr Kas / Cr Uang Jaminan (2-1200) — a LIABILITY, never income.
 * Shared by RentalsTable and the Customer Deposits page so the two can't drift.
 */
class ReceiveDepositAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'receive_deposit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Receive Deposit')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->visible(fn (Rental $record): bool => $record->deposit > 0
                && in_array($record->security_deposit_status, ['pending', null], true))
            ->form([
                Select::make('finance_account_id')
                    ->label('Account')
                    ->options(FinanceAccount::where('is_active', true)->pluck('name', 'id'))
                    ->required(),
                TextInput::make('amount')
                    ->label('Amount Received')
                    ->numeric()
                    ->prefix('Rp')
                    ->default(fn (Rental $record) => $record->deposit)
                    ->required(),
                DatePicker::make('transaction_date')
                    ->default(now())
                    ->required(),
            ])
            ->action(function (Rental $record, array $data): void {
                $transaction = new FinanceTransaction([
                    'finance_account_id' => $data['finance_account_id'],
                    'type' => FinanceTransaction::TYPE_DEPOSIT_IN,
                    'amount' => $data['amount'],
                    'description' => 'Security Deposit for Rental ' . $record->rental_code,
                    'category' => 'Security Deposit In',
                    'date' => $data['transaction_date'],
                ]);
                $transaction->reference()->associate($record);
                $transaction->save();

                RentalAccountingService::postDepositReceived(
                    $record,
                    (int) $data['finance_account_id'],
                    (float) $data['amount'],
                    $data['transaction_date']
                );

                $record->update([
                    'security_deposit_status' => 'held',
                    'security_deposit_amount' => $data['amount'],
                ]);

                Notification::make()->title('Deposit Received')->success()->send();
            });
    }
}
