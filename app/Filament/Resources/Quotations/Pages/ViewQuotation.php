<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Actions\ConvertQuotationToInvoiceAction;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\URL;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    public function getView(): string
    {
        return 'filament.resources.quotations.pages.view-quotation';
    }

    public function getTitle(): string
    {
        return 'Quotation ' . $this->getRecord()->number;
    }

    public function getQuotationData(): Quotation
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
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ConvertQuotationToInvoiceAction::make(),

            Action::make('send_quotation')
                ->label('Mark as Sent')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (Quotation $record) => $record->status !== Quotation::STATUS_ACCEPTED)
                ->action(function (Quotation $record) {
                    $record->update(['status' => Quotation::STATUS_SENT]);
                    Notification::make()->title('Quotation sent')->success()->send();
                }),

            ActionGroup::make([
                Action::make('print_quotation')
                    ->label('Print / Download')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->action(function (Quotation $record) {
                        foreach ($record->rentals as $rental) {
                            foreach ($rental->items as $item) {
                                $item->attachKitsFromUnit();
                            }
                        }

                        $record->load(['customer', 'rentals.items.productUnit.product', 'rentals.items.product', 'rentals.items.productVariation', 'rentals.items.rentalItemKits.unitKit']);

                        $pdf = Pdf::loadView('pdf.quotation', ['quotation' => $record]);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            'Quotation-' . $record->number . '.pdf'
                        );
                    }),

                Action::make('send_whatsapp_quotation')
                    ->label('Send via WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn () => Setting::get('whatsapp_enabled', true))
                    ->disabled(fn (Quotation $record) => empty($record->customer->phone))
                    ->tooltip(fn (Quotation $record) => empty($record->customer->phone) ? 'Customer phone number is missing' : null)
                    ->url(function (Quotation $record) {
                        $customer = $record->customer;
                        if (empty($customer->phone)) {
                            return '#';
                        }

                        $pdfLink = URL::signedRoute('public-documents.quotation', ['quotation' => $record]);

                        $message = \App\Helpers\WhatsAppHelper::parseTemplate('whatsapp_template_quotation', [
                            'customer_name' => $customer->name,
                            'quotation_ref' => $record->number,
                            'total_amount' => 'Rp ' . number_format($record->total, 0, ',', '.'),
                            'valid_until' => $record->valid_until ? $record->valid_until->format('d M Y') : '-',
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
