<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Helpers\WhatsAppHelper;
use App\Models\Delivery;
use App\Models\Rental;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\URL;

class RentalDocuments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = RentalResource::class;

    public ?Rental $rental = null;

    public function getView(): string
    {
        return 'filament.resources.rentals.pages.rental-documents';
    }

    public function mount(int|string $record): void
    {
        $this->rental = Rental::with(['customer', 'deliveries'])->findOrFail($record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Surat Jalan - ' . $this->rental->rental_code;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->rental->deliveries()->getQuery())
            ->columns([
                TextColumn::make('delivery_number')
                    ->label('Nomor Surat Jalan')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Arah')
                    ->badge()
                    ->color(fn (string $state): string => Delivery::getTypeColor($state))
                    ->formatStateUsing(fn (string $state): string => $state === 'out' ? 'Keluar (SJK)' : 'Masuk (SJM)'),

                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => Delivery::getStatusColor($state))
                    ->formatStateUsing(fn (string $state): string => Delivery::getStatusOptions()[$state] ?? ucfirst($state)),

                IconColumn::make('recipient_signature')
                    ->label('TTD')
                    ->boolean()
                    ->trueIcon('heroicon-o-pencil-square')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->state(fn (Delivery $record): bool => $record->isSigned())
                    ->tooltip(fn (Delivery $record): ?string => $record->isSigned()
                        ? 'Ditandatangani ' . ($record->recipient_name ? 'oleh ' . $record->recipient_name : '') . ' • ' . $record->signed_at?->format('d M Y H:i')
                        : 'Belum ditandatangani'),
            ])
            ->recordActions([
                Action::make('process')
                    ->label(fn (Delivery $record): string => $record->type === 'out' ? 'Proses Keluar' : 'Proses Masuk')
                    ->icon(fn (Delivery $record): string => $record->type === 'out' ? 'heroicon-o-truck' : 'heroicon-o-arrow-uturn-left')
                    ->color(fn (Delivery $record): string => $record->type === 'out' ? 'warning' : 'success')
                    ->url(fn (Delivery $record): string => $record->type === 'out'
                        ? RentalResource::getUrl('pickup', ['record' => $this->rental])
                        : RentalResource::getUrl('return', ['record' => $this->rental])
                    )
                    ->visible(fn (Delivery $record): bool => $record->status !== Delivery::STATUS_COMPLETED
                        && $record->status !== Delivery::STATUS_CANCELLED),

                Action::make('view_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(fn (Delivery $record) => $this->downloadDeliveryPdf($record)),

                Action::make('sign')
                    ->label(fn (Delivery $record): string => $record->isSigned() ? 'Ubah TTD' : 'Tanda Tangan')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->modalHeading('Tanda Tangan Penerima')
                    ->modalDescription(fn (Delivery $record): string => 'Surat Jalan ' . $record->delivery_number)
                    ->modalWidth('lg')
                    ->fillForm(fn (Delivery $record): array => [
                        'recipient_name' => $record->recipient_name ?? $this->rental->customer?->name,
                        'recipient_signature' => $record->recipient_signature,
                    ])
                    ->form([
                        TextInput::make('recipient_name')
                            ->label('Nama Penerima')
                            ->required(),

                        ViewField::make('recipient_signature')
                            ->label('Tanda Tangan')
                            ->view('forms.components.signature-pad'),
                    ])
                    ->action(function (Delivery $record, array $data): void {
                        $signed = ! empty($data['recipient_signature']);

                        $record->update([
                            'recipient_name' => $data['recipient_name'],
                            'recipient_signature' => $data['recipient_signature'] ?: null,
                            'signed_at' => $signed ? now() : null,
                        ]);

                        Notification::make()
                            ->title($signed ? 'Tanda tangan tersimpan' : 'Tanda tangan dikosongkan')
                            ->success()
                            ->send();
                    }),

                Action::make('send_whatsapp')
                    ->label('Kirim')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->visible(fn (): bool => (bool) Setting::get('whatsapp_enabled', true))
                    ->disabled(fn (): bool => empty($this->rental->customer?->phone))
                    ->tooltip(fn (): ?string => empty($this->rental->customer?->phone) ? 'Nomor telepon customer kosong' : null)
                    ->url(fn (Delivery $record): string => $this->whatsappLink($record))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_all')
                ->label('Cetak Semua Surat Jalan')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->visible(fn (): bool => $this->rental->deliveries()->exists())
                ->action(function () {
                    $deliveries = $this->rental->deliveries()
                        ->with(['rental.user', 'items.rentalItem.productUnit.product', 'items.rentalItem.productUnit.variation', 'items.rentalItemKit.unitKit', 'checkedBy'])
                        ->orderBy('type')
                        ->get();

                    $pdf = Pdf::loadView('pdf.delivery-notes-batch', ['deliveries' => $deliveries]);

                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        'SuratJalan-' . $this->rental->rental_code . '.pdf'
                    );
                }),
        ];
    }

    protected function downloadDeliveryPdf(Delivery $delivery)
    {
        $delivery->load(['rental.user', 'items.rentalItem.productUnit.product', 'items.rentalItem.productUnit.variation', 'items.rentalItemKit.unitKit', 'checkedBy']);

        $pdf = Pdf::loadView('pdf.delivery-note', ['delivery' => $delivery]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $delivery->delivery_number . '.pdf'
        );
    }

    protected function whatsappLink(Delivery $delivery): string
    {
        $customer = $this->rental->customer;

        if (empty($customer?->phone)) {
            return '#';
        }

        $templateKey = $delivery->type === 'out'
            ? 'whatsapp_template_rental_delivery_in'
            : 'whatsapp_template_rental_delivery_out';

        $pdfLink = URL::signedRoute('public-documents.rental.delivery-note', ['rental' => $this->rental]);

        $message = WhatsAppHelper::parseTemplate($templateKey, [
            'customer_name' => $customer->name,
            'rental_ref' => $this->rental->rental_code,
            'link_pdf' => $pdfLink,
            'company_name' => Setting::get('site_name', 'Gearent'),
        ]);

        return WhatsAppHelper::getLink($customer->phone, $message);
    }
}
