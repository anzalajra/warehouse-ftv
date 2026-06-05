<?php

namespace App\Filament\Resources\Deliveries\Tables;

use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Delivery;
use App\Models\Rental;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class DeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rental_code')
                    ->label('Rental')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Rental $record): ?string => $record->customer?->name),

                TextColumn::make('window')
                    ->label('Periode Sewa')
                    ->state(fn (Rental $record): string => $record->start_date?->format('d M') . ' → ' . $record->end_date?->format('d M Y'))
                    ->icon('heroicon-o-calendar-days')
                    ->visibleFrom('md'),

                TextColumn::make('out_status')
                    ->label('Keluar (SJK)')
                    ->badge()
                    ->state(fn (Rental $record): string => self::movementLabel($record->outDelivery))
                    ->color(fn (Rental $record): string => self::movementColor($record->outDelivery))
                    ->description(fn (Rental $record): ?string => $record->outDelivery?->delivery_number),

                TextColumn::make('in_status')
                    ->label('Masuk (SJM)')
                    ->badge()
                    ->state(fn (Rental $record): string => self::movementLabel($record->inDelivery))
                    ->color(fn (Rental $record): string => self::movementColor($record->inDelivery))
                    ->description(fn (Rental $record): ?string => $record->inDelivery?->delivery_number),

                TextColumn::make('status')
                    ->label('Status Rental')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Rental::getStatusOptions()[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => Rental::getStatusColor($state))
                    ->visibleFrom('lg'),
            ])
            ->defaultSort('start_date', 'asc')
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('print_surat_jalan')
                        ->label('Cetak Surat Jalan')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $deliveries = Delivery::whereIn('rental_id', $records->pluck('id'))
                                ->with([
                                    'rental.user',
                                    'items.rentalItem.productUnit.product',
                                    'items.rentalItem.productUnit.variation',
                                    'items.rentalItemKit.unitKit',
                                    'checkedBy',
                                ])
                                ->orderBy('rental_id')
                                ->orderBy('type')
                                ->get();

                            if ($deliveries->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak ada surat jalan')
                                    ->body('Rental terpilih belum memiliki surat jalan.')
                                    ->warning()
                                    ->send();

                                return null;
                            }

                            $pdf = Pdf::loadView('pdf.delivery-notes-batch', ['deliveries' => $deliveries]);

                            return response()->streamDownload(
                                fn () => print($pdf->output()),
                                'SuratJalan-batch-' . now()->format('Ymd-His') . '.pdf'
                            );
                        }),
                ]),
            ])
            ->recordActions([
                Action::make('pickup')
                    ->label('Proses Keluar')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->url(fn (Rental $record): string => RentalResource::getUrl('pickup', ['record' => $record]))
                    ->visible(fn (Rental $record): bool => in_array($record->status, [
                        Rental::STATUS_CONFIRMED,
                        Rental::STATUS_LATE_PICKUP,
                    ])),

                Action::make('return')
                    ->label('Proses Masuk')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->url(fn (Rental $record): string => RentalResource::getUrl('return', ['record' => $record]))
                    ->visible(fn (Rental $record): bool => in_array($record->status, [
                        Rental::STATUS_ACTIVE,
                        Rental::STATUS_LATE_RETURN,
                        Rental::STATUS_PARTIAL_RETURN,
                    ])),

                Action::make('open')
                    ->label('Buka')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (Rental $record): string => RentalResource::getUrl('delivery', ['record' => $record])),
            ])
            ->recordUrl(fn (Rental $record): string => RentalResource::getUrl('delivery', ['record' => $record]));
    }

    /**
     * Human label for a delivery slot on the board: "Belum dibuat" when the
     * surat jalan has not been generated yet, otherwise its status.
     */
    protected static function movementLabel(?Delivery $delivery): string
    {
        if (! $delivery) {
            return 'Belum dibuat';
        }

        return Delivery::getStatusOptions()[$delivery->status] ?? ucfirst($delivery->status);
    }

    protected static function movementColor(?Delivery $delivery): string
    {
        if (! $delivery) {
            return 'gray';
        }

        return Delivery::getStatusColor($delivery->status);
    }
}
