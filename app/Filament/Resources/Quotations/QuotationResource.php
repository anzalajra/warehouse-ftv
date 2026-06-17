<?php

namespace App\Filament\Resources\Quotations;

use App\Filament\Actions\ConvertQuotationToInvoiceAction;
use App\Filament\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Resources\Quotations\Pages\EditQuotation;
use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Filament\Resources\Quotations\RelationManagers\RentalsRelationManager;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\ActionGroup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Quotation Details')
                    ->schema([
                        TextInput::make('number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated'),
                        Select::make('user_id')
                            ->relationship('customer', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        DatePicker::make('valid_until'),
                        Select::make('status')
                            ->options(Quotation::getStatusOptions())
                            ->required()
                            ->default(Quotation::STATUS_ON_QUOTE),
                        TextInput::make('total')
                            ->disabled()
                            ->prefix('Rp')
                            ->numeric(),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('sm'),
                TextColumn::make('valid_until')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('total')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('sm'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Quotation::getStatusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Quotation::STATUS_ON_QUOTE => 'warning',
                        Quotation::STATUS_SENT => 'info',
                        Quotation::STATUS_ACCEPTED => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Quotation::getStatusOptions()),
            ])
            ->recordUrl(fn (Quotation $record): string => ViewQuotation::getUrl(['record' => $record]))
            ->recordActions([
                // Primary: turn this quotation into an invoice in one click.
                ConvertQuotationToInvoiceAction::make(),

                ActionGroup::make([
                    ViewAction::make()
                        ->url(fn (Quotation $record): string => ViewQuotation::getUrl(['record' => $record])),
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
                                fn () => print($pdf->output()),
                                'Quotation-' . $record->number . '.pdf'
                            );
                        }),
                    Action::make('mark_sent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->visible(fn (Quotation $record) => $record->status !== Quotation::STATUS_ACCEPTED)
                        ->action(function (Quotation $record) {
                            $record->update(['status' => Quotation::STATUS_SENT]);
                            Notification::make()->title('Quotation sent')->success()->send();
                        }),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RentalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotations::route('/'),
            'create' => CreateQuotation::route('/create'),
            'view' => ViewQuotation::route('/{record}'),
            'edit' => EditQuotation::route('/{record}/edit'),
        ];
    }
}
