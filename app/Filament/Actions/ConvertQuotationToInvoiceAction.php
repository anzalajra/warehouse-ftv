<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Invoice;
use App\Models\Quotation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * One-click conversion of a quotation into an invoice (replaces the old
 * buried Status → Accepted → checkbox flow).
 *
 * Creates the invoice, moves every rental onto it, marks the quotation
 * Accepted, then recalculates the invoice from its rentals (the source of
 * truth) and lands the user on the new invoice's View page.
 *
 * Guarded so a quotation can't be double-converted.
 */
class ConvertQuotationToInvoiceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'convert_to_invoice';
    }

    public static function alreadyConverted(Quotation $record): bool
    {
        return Invoice::where('quotation_id', $record->id)->exists()
            || $record->rentals()->whereNotNull('invoice_id')->exists();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Convert to Invoice')
            ->icon('heroicon-o-document-currency-dollar')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Convert to Invoice')
            ->modalDescription('Create an invoice from this quotation and move its rentals onto it. The quotation will be marked as Accepted.')
            ->visible(fn (Quotation $record): bool => $record->rentals()->exists()
                && ! static::alreadyConverted($record))
            ->action(function (Quotation $record) {
                $invoice = Invoice::create([
                    'quotation_id' => $record->id,
                    'user_id' => $record->user_id,
                    'date' => now(),
                    'due_date' => now()->addDays(7),
                    'status' => Invoice::STATUS_WAITING_FOR_PAYMENT,
                    'subtotal' => $record->subtotal,
                    'tax' => $record->tax,
                    'total' => $record->total,
                    'notes' => $record->notes,
                ]);

                $record->rentals()->update(['invoice_id' => $invoice->id]);
                $record->update(['status' => Quotation::STATUS_ACCEPTED]);

                // Derive totals + status from the linked rentals + any existing payments.
                $invoice->recalculate();

                Notification::make()
                    ->title('Invoice created')
                    ->success()
                    ->send();

                return redirect()->to(ViewInvoice::getUrl(['record' => $invoice]));
            });
    }
}
