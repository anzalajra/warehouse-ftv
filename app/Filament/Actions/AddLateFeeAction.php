<?php

namespace App\Filament\Actions;

use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Single source of truth for adding a late fee to an invoice.
 *
 * invoice.late_fee / invoice.total are DERIVED (Invoice::recalculate sums the
 * rentals), so the fee is written onto the underlying rental — the source of
 * truth — then the invoice is re-synced. Mirrors the RentalsTable Finance
 * group: writes rental.late_fee, recalculateTotal(), logs the activity, then
 * syncOutstandingInvoice() so a never-invoiced balance still surfaces in
 * Accounts Receivable.
 */
class AddLateFeeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'add_late_fee';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Add Late Fee')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('warning')
            ->visible(fn (Invoice $record): bool => $record->status !== Invoice::STATUS_PAID
                && $record->status !== 'cancelled')
            ->form([
                TextInput::make('late_fee_amount')
                    ->label('Late Fee Amount')
                    ->required()
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(1),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required(),
            ])
            ->action(function (Invoice $record, array $data): void {
                $amount = (float) $data['late_fee_amount'];

                $rental = $record->rentals()->first();
                if (! $rental) {
                    Notification::make()
                        ->title('No rental linked')
                        ->body('This invoice has no rental to attach the late fee to.')
                        ->danger()
                        ->send();

                    return;
                }

                $rental->late_fee = ($rental->late_fee ?? 0) + $amount;
                $rental->recalculateTotal();

                // Audit in the rental activity log (not the free-text notes).
                $rental->logActivity(
                    'Late fee Rp ' . number_format($amount, 0, ',', '.') . ' ditambahkan. Alasan: ' . $data['reason'],
                    'general'
                );

                // Create/reopen + re-aggregate the invoice from its rentals.
                $rental->syncOutstandingInvoice('late fee');
                $record->recalculate();

                Notification::make()
                    ->title('Late Fee Added')
                    ->success()
                    ->send();
            });
    }
}
