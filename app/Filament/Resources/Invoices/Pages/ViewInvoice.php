<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Actions\AddLateFeeAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\URL;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getView(): string
    {
        return 'filament.resources.invoices.pages.view-invoice';
    }

    public function getTitle(): string
    {
        return 'Invoice ' . $this->getRecord()->number;
    }

    /**
     * Invoice with everything the View blade renders (line items, kits,
     * payment transactions, discount-breakdown relations).
     */
    public function getInvoiceData(): Invoice
    {
        return $this->getRecord()->loadMissing([
            'customer',
            'rentals.items.productUnit.product',
            'rentals.items.product',
            'rentals.items.productVariation',
            'rentals.items.rentalItemKits.unitKit',
            'rentals.dailyDiscount',
            'rentals.datePromotion',
            'rentals.discountRelation',
            'transactions.account',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordPaymentAction::make(),
            AddLateFeeAction::make(),

            ActionGroup::make([
                Action::make('print_invoice')
                    ->label('Print / Download')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->action(function (Invoice $record) {
                        foreach ($record->rentals as $rental) {
                            foreach ($rental->items as $item) {
                                $item->attachKitsFromUnit();
                            }
                        }

                        $record->load(['customer', 'rentals.items.productUnit.product', 'rentals.items.product', 'rentals.items.productVariation', 'rentals.items.rentalItemKits.unitKit']);

                        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $record]);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            'Invoice-' . $record->number . '.pdf'
                        );
                    }),

                Action::make('send_whatsapp_invoice')
                    ->label('Send via WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn () => Setting::get('whatsapp_enabled', true))
                    ->disabled(fn (Invoice $record) => empty($record->customer->phone))
                    ->tooltip(fn (Invoice $record) => empty($record->customer->phone) ? 'Customer phone number is missing' : null)
                    ->url(function (Invoice $record) {
                        $customer = $record->customer;
                        if (empty($customer->phone)) {
                            return '#';
                        }

                        $pdfLink = URL::signedRoute('public-documents.invoice', ['invoice' => $record]);

                        $message = \App\Helpers\WhatsAppHelper::parseTemplate('whatsapp_template_invoice', [
                            'customer_name' => $customer->name,
                            'invoice_ref' => $record->number,
                            'total_amount' => 'Rp ' . number_format($record->total, 0, ',', '.'),
                            'due_date' => $record->due_date ? $record->due_date->format('d M Y') : '-',
                            'link_pdf' => $pdfLink,
                            'company_name' => Setting::get('site_name', 'Gearent'),
                        ]);

                        return \App\Helpers\WhatsAppHelper::getLink($customer->phone, $message);
                    })
                    ->openUrlInNewTab(),

                EditAction::make(),
                DeleteAction::make(),
            ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->color('gray'),
        ];
    }
}
