<?php

namespace App\Filament\Actions;

use App\Models\Invoice;
use App\Models\Rental;
use App\Services\RentalAccountingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Retroactively change the manual discount on a COMPLETED rental — the light-weight
 * alternative to Reopen Rental when the intent is only to correct the amount.
 *
 * Unlike reopen it does NOT flip the status to ACTIVE, touch units/deliveries, or
 * clear revenue_recognized_at, so there is no re-return and no double-posted revenue.
 * It writes the manual discount, recalculates the total (recalculateTotal() folds in
 * every discount layer + deposit + tax), posts a GL adjustment for the revenue/AR
 * delta in advanced finance mode, then re-syncs the invoice so Accounts Receivable
 * reflects the new balance.
 *
 * Only offered on COMPLETED rentals — every editable status already exposes the
 * discount picker inside the RentalEditor.
 */
class AdjustDiscountAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'adjust_discount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Adjust Discount')
            ->icon('heroicon-o-receipt-percent')
            ->color('warning')
            ->visible(fn (Rental $record): bool => $record->status === Rental::STATUS_COMPLETED
                && Auth::user()?->hasRole(['super_admin', 'admin']))
            ->requiresConfirmation()
            ->modalHeading('Adjust discount on completed rental')
            ->modalDescription('Changes the total without reopening the rental. The linked invoice / Accounts Receivable balance is updated. Revenue postings are adjusted in advanced finance mode.')
            ->form([
                Select::make('discount_type')
                    ->label('Discount Type')
                    ->options([
                        'fixed' => 'Fixed (Rp)',
                        'percent' => 'Percentage (%)',
                    ])
                    ->default(fn (Rental $record) => $record->discount_type ?: 'fixed')
                    ->required()
                    ->live(),
                TextInput::make('discount')
                    ->label('Discount')
                    ->numeric()
                    ->minValue(0)
                    ->default(fn (Rental $record) => $record->discount)
                    ->prefix(fn (Get $get) => $get('discount_type') === 'percent' ? null : 'Rp')
                    ->suffix(fn (Get $get) => $get('discount_type') === 'percent' ? '%' : null)
                    ->maxValue(fn (Get $get) => $get('discount_type') === 'percent' ? 100 : null)
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->placeholder('e.g. koreksi harga / kompensasi keterlambatan kirim'),
            ])
            ->action(function (Rental $record, array $data): void {
                // Capture the pre-recalc revenue/PPN so the GL adjustment can post the
                // exact delta (mirrors RentalAccountingService::rentalNetRevenue()).
                $prevNetRevenue = (float) $record->total
                    - (float) $record->security_deposit_amount
                    - (float) ($record->ppn_amount ?? 0)
                    - (float) ($record->late_fee ?? 0);
                $prevPpn = (float) ($record->ppn_amount ?? 0);
                $prevTotal = (float) $record->total;
                $hadInvoice = (bool) $record->invoice_id;

                // Manual discount overrides any coupon (they share the `discount` column).
                $record->discount = (float) $data['discount'];
                $record->discount_type = $data['discount_type'];
                $record->discount_id = null;
                $record->discount_code = null;
                $record->recalculateTotal();

                // Adjust the already-issued invoice's revenue/receivable for the delta.
                // Skipped when no invoice existed yet — syncOutstandingInvoice() below
                // recognizes revenue for the first time at the new total instead.
                if ($hadInvoice) {
                    RentalAccountingService::postDiscountAdjustment($record, $prevNetRevenue, $prevPpn);
                }

                $record->logActivity(
                    'Diskon disesuaikan menjadi '
                        . ($data['discount_type'] === 'percent'
                            ? rtrim(rtrim((string) $data['discount'], '0'), '.') . '%'
                            : 'Rp ' . number_format((float) $data['discount'], 0, ',', '.'))
                        . '. Total: Rp ' . number_format($prevTotal, 0, ',', '.')
                        . ' → Rp ' . number_format((float) $record->total, 0, ',', '.')
                        . '. Alasan: ' . $data['reason'],
                    'general'
                );

                Log::info('Rental DISCOUNT ADJUST', [
                    'rental_id' => $record->id,
                    'by' => Auth::id(),
                    'prev_total' => $prevTotal,
                    'new_total' => (float) $record->total,
                    'reason' => $data['reason'],
                ]);

                // Recalc the linked invoice (or issue one when a balance is now owed).
                $result = $record->syncOutstandingInvoice('discount adjustment');

                // Overpayment warning: a discount below what was already paid leaves a
                // credit that must be refunded via the Deposit/Refund flow — not automatic.
                $invoice = $result['invoice'] ?? ($record->invoice_id ? Invoice::find($record->invoice_id) : null);
                if ($invoice && $invoice->status === Invoice::STATUS_PAID && $invoice->balance < -0.01) {
                    Notification::make()
                        ->title('Discount Applied — Overpayment')
                        ->body('The new total is below the amount already paid (credit of Rp '
                            . number_format(abs($invoice->balance), 0, ',', '.')
                            . '). Refund it manually via Finance if needed.')
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Discount Adjusted')
                    ->body('New total: Rp ' . number_format((float) $record->total, 0, ',', '.') . '.')
                    ->success()
                    ->send();
            });
    }
}
