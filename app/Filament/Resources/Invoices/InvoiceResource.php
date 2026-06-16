<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\RelationManagers\RentalsRelationManager;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Account;
use App\Services\JournalService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

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
                            ->preload(),
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
                TextColumn::make('status')
                    ->badge()
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
                //
            ])
            ->recordActions([
                Action::make('record_payment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record) => $record->status !== Invoice::STATUS_PAID && $record->status !== 'cancelled' && ($record->total - $record->paid_amount) > 0)
                    ->form(function (Invoice $record) {
                        return [
                            Select::make('finance_account_id')
                                ->label('Deposit To Account')
                                ->options(FinanceAccount::where('is_active', true)->pluck('name', 'id'))
                                ->required(),
                            TextInput::make('amount')
                                ->label('Amount')
                                ->required()
                                ->numeric()
                                ->prefix('Rp')
                                ->default($record->total - $record->paid_amount)
                                ->maxValue($record->total - $record->paid_amount),
                            DatePicker::make('date')
                                ->label('Payment Date')
                                ->default(now())
                                ->required(),
                            Select::make('payment_method')
                                ->label('Payment Method')
                                ->options([
                                    'Cash' => 'Cash',
                                    'Transfer' => 'Bank Transfer',
                                    'QRIS' => 'QRIS',
                                    'Credit Card' => 'Credit Card',
                                ])
                                ->required(),
                            Textarea::make('notes')
                                ->label('Notes'),
                        ];
                    })
                    ->action(function (Invoice $record, array $data) {
                        $transaction = new FinanceTransaction([
                            'finance_account_id' => $data['finance_account_id'],
                            'user_id' => Auth::id(),
                            'type' => FinanceTransaction::TYPE_INCOME,
                            'amount' => $data['amount'],
                            'date' => $data['date'],
                            'category' => 'Invoice Payment',
                            'description' => 'Payment for Invoice #' . $record->number,
                            'payment_method' => $data['payment_method'],
                            'notes' => $data['notes'] ?? null,
                        ]);
                        $transaction->reference()->associate($record);
                        $transaction->save();

                        // Recalculate invoice status
                        $record->recalculate();

                        // Journal Entry: Debit 1-1100 (Kas/Bank), Credit 2-1300 (Pendapatan Diterima Dimuka)
                        $financeAccount = FinanceAccount::find($data['finance_account_id']);
                        $debitAccountId = $financeAccount?->linked_account_id;
                        
                        // Default to 2-1300 Pendapatan Diterima Dimuka for Invoice Payments (usually Rentals)
                        $creditAccount = Account::where('code', '2-1300')->first();
                        $creditAccountId = $creditAccount?->id;

                        if ($debitAccountId && $creditAccountId) {
                            JournalService::createEntry(
                                $record,
                                'Payment for Invoice #' . $record->number,
                                [
                                    [
                                        'account_id' => $debitAccountId,
                                        'debit' => $data['amount'],
                                        'credit' => 0,
                                    ],
                                    [
                                        'account_id' => $creditAccountId,
                                        'debit' => 0,
                                        'credit' => $data['amount'],
                                    ],
                                ],
                                $data['date']
                            );
                        }

                        Notification::make()
                            ->title('Payment Recorded')
                            ->success()
                            ->send();
                    }),

                Action::make('add_late_fee')
                    ->label('Add Late Fee')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->visible(fn (Invoice $record) => $record->status !== Invoice::STATUS_PAID && $record->status !== 'cancelled')
                    ->form([
                        TextInput::make('late_fee_amount')
                            ->label('Late Fee Amount')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(1),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required(),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        $amount = (float) $data['late_fee_amount'];

                        // invoice.late_fee is DERIVED (Invoice::recalculate sums rentals.late_fee)
                        // and invoice.total = Σ rentals.total. Writing the fee onto the invoice
                        // row alone is immediately wiped by recalculate(), so apply it to the
                        // underlying rental — the source of truth — then re-aggregate.
                        $rental = $record->rentals()->first();
                        if (! $rental) {
                            Notification::make()
                                ->title('No rental linked')
                                ->body('This invoice has no rental to attach the late fee to.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $rental->late_fee = ($rental->late_fee ?? 0) + $amount;
                        $rental->notes = trim(($rental->notes ?? '') . "\n[Late Fee] Rp " . number_format($amount, 0, ',', '.') . ' - Reason: ' . $data['reason']);
                        $rental->recalculateTotal();

                        // Re-aggregate the invoice (total, late_fee, status) from its rentals.
                        $record->recalculate();

                        Notification::make()
                            ->title('Late Fee Added')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
                Action::make('change_status')
                    ->label('Status')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Select::make('status')
                            ->options(Invoice::getStatusOptions())
                            ->required(),
                    ])
                    ->action(function (array $data, Invoice $record) {
                        $record->update(['status' => $data['status']]);
                    }),
                Action::make('print_invoice')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->action(function (Invoice $record) {
                        foreach ($record->rentals as $rental) {
                            foreach ($rental->items as $item) {
                                $item->attachKitsFromUnit();
                            }
                        }
                        
                        $record->load(['customer', 'rentals.items.productUnit.product', 'rentals.items.rentalItemKits.unitKit']);
                        
                        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $record]);
                        
                        return response()->streamDownload(
                            fn () => print($pdf->output()),
                            'Invoice-' . $record->number . '.pdf'
                        );
                    }),
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
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
