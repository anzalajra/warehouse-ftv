<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Actions\AddLateFeeAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\RentalsRelationManager;
use App\Models\Invoice;
use App\Models\Rental;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Schemas\Components\Utilities\Get;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Details')
                    ->schema([
                        TextInput::make('number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated'),
                        Select::make('user_id')
                            ->relationship('customer', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        DatePicker::make('due_date'),
                        Select::make('status')
                            ->options(Invoice::getStatusOptions())
                            ->required()
                            ->default(Invoice::STATUS_SENT),
                        TextInput::make('total')
                            ->disabled()
                            ->prefix('Rp')
                            ->numeric(),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                        // Manual creation shortcut: attach unbilled rentals right here
                        // instead of create-blank-then-add-via-relation-manager.
                        // Handled in CreateInvoice::afterCreate (not a real column).
                        Select::make('rental_ids')
                            ->label('Attach Rentals')
                            ->helperText('Pick the customer\'s rentals to bill on this invoice. Totals are recalculated automatically.')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->dehydrated(false)
                            ->visibleOn('create')
                            ->options(fn (Get $get): array => $get('user_id')
                                ? Rental::where('user_id', $get('user_id'))
                                    ->whereNull('invoice_id')
                                    ->pluck('rental_code', 'id')
                                    ->all()
                                : [])
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
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('total')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('sm'),
                TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->money('IDR')
                    ->state(fn (Invoice $record): float => $record->balance)
                    ->color(fn (Invoice $record): string => $record->balance > 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Invoice::getStatusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Invoice::STATUS_SENT => 'info',
                        Invoice::STATUS_NEGOTIATION => 'warning',
                        Invoice::STATUS_WAITING_FOR_PAYMENT => 'warning',
                        Invoice::STATUS_PAID => 'success',
                        Invoice::STATUS_PARTIAL => 'info',
                        default => 'gray',
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Invoice::getStatusOptions()),
                Filter::make('outstanding')
                    ->label('Outstanding only')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', '!=', Invoice::STATUS_PAID)
                        ->whereColumn('total', '>', 'paid_amount')),
            ])
            ->recordUrl(fn (Invoice $record): string => ViewInvoice::getUrl(['record' => $record]))
            ->recordActions([
                // Primary contextual action: collect payment when a balance is owed.
                RecordPaymentAction::make(),

                // Everything else tucked behind one overflow menu to keep the row clean.
                ActionGroup::make([
                    ViewAction::make()
                        ->url(fn (Invoice $record): string => ViewInvoice::getUrl(['record' => $record])),
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
                                fn () => print($pdf->output()),
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
                    AddLateFeeAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RentalsRelationManager::class,
            \App\Filament\Resources\Invoices\RelationManagers\FinanceTransactionsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Resources\Invoices\Widgets\InvoiceStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
