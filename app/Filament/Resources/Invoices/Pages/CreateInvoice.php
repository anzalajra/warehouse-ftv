<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Rental;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    /**
     * Link the rentals chosen in the "Attach Rentals" field (excluded from the
     * model fill via dehydrated(false)) and recalculate the invoice totals — so
     * manual creation is one step instead of create-then-add-via-relation-manager.
     */
    protected function afterCreate(): void
    {
        $rentalIds = $this->data['rental_ids'] ?? [];

        if (! empty($rentalIds)) {
            Rental::whereIn('id', $rentalIds)
                ->whereNull('invoice_id')
                ->update(['invoice_id' => $this->record->id]);

            $this->record->recalculate();
        }
    }
}
