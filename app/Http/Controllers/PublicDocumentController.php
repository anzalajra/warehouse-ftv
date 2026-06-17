<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Rental;
use App\Models\Delivery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PublicDocumentController extends Controller
{
    public function rentalChecklist(Request $request, Rental $rental)
    {
        if (!$request->hasValidSignature()) {
            abort(403);
        }

        $rental->load(['user', 'items.productUnit.product', 'items.productUnit.kits', 'items.rentalItemKits.unitKit']);
        
        $pdf = Pdf::loadView('pdf.checklist-form', ['rental' => $rental]);
        
        return $pdf->stream('Checklist-' . $rental->rental_code . '.pdf');
    }

    public function rentalDeliveryNote(Request $request, Rental $rental)
    {
        if (!$request->hasValidSignature()) {
            abort(403);
        }

        // Try to find the first delivery for this rental
        $delivery = $rental->deliveries()->latest()->first();

        if (!$delivery) {
            abort(404, 'No delivery found for this rental.');
        }

        return $this->deliveryNote($request, $delivery);
    }

    public function deliveryNote(Request $request, Delivery $delivery)
    {
        if (!$request->hasValidSignature()) {
            abort(403);
        }

        $delivery->load(['rental.user', 'rental.items.productUnit.product', 'items.rentalItem.productUnit', 'items.rentalItemKit.unitKit', 'checkedBy']);
        
        $pdf = Pdf::loadView('pdf.delivery-note', ['delivery' => $delivery]);
        
        return $pdf->stream('DeliveryNote-' . $delivery->delivery_number . '.pdf');
    }

    public function quotation(Request $request, Quotation $quotation)
    {
        if (!$request->hasValidSignature()) {
            abort(403);
        }

        foreach ($quotation->rentals as $rental) {
            foreach ($rental->items as $item) {
                $item->attachKitsFromUnit();
            }
        }

        $quotation->load(['user', 'rentals.items.productUnit.product', 'rentals.items.product', 'rentals.items.productVariation', 'rentals.items.rentalItemKits.unitKit']);
        
        $pdf = Pdf::loadView('pdf.quotation', ['quotation' => $quotation]);
        
        return $pdf->stream('Quotation-' . $quotation->number . '.pdf');
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        if (!$request->hasValidSignature()) {
            abort(403);
        }

        $invoice->load(['user', 'rentals.items.productUnit.product', 'rentals.items.product', 'rentals.items.productVariation', 'rentals.items.rentalItemKits.unitKit']);
        
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);
        
        return $pdf->stream('Invoice-' . $invoice->number . '.pdf');
    }
}
