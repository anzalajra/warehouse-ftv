<?php

namespace App\Filament\Clusters\Finance\Resources;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Resources\BillResource\Pages;
use App\Models\Bill;
use BackedEnum;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static ?string $cluster = FinanceCluster::class;

    protected static ?string $navigationLabel = 'Accounts Payable';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bill Details')
                    ->schema([
                        Forms\Components\TextInput::make('vendor_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bill_number')
                            ->label('Bill / Invoice Number')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('bill_date')
                            ->required()
                            ->default(now()),
                        Forms\Components\DatePicker::make('due_date')
                            ->required()
                            ->default(now()->addDays(30)),
                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('Rp'),
                        Forms\Components\Select::make('category')
                            ->options([
                                'Utilities' => 'Utilities',
                                'Inventory' => 'Inventory',
                                'Service' => 'Service',
                                'Rent' => 'Rent',
                                'Other' => 'Other',
                            ])
                            ->searchable()
                            ->preload(),
                        Forms\Components\FileUpload::make('proof_document')
                            ->disk('local')
                            ->directory('bills')
                            ->visibility('private'),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bill_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Due')
                    ->money('IDR')
                    ->state(fn (Bill $record): float => $record->amount - $record->paid_amount),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Bill::STATUS_PENDING => 'warning',
                        Bill::STATUS_PARTIAL => 'info',
                        Bill::STATUS_PAID => 'success',
                        Bill::STATUS_OVERDUE => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Bill::STATUS_PENDING => 'Pending',
                        Bill::STATUS_PARTIAL => 'Partial',
                        Bill::STATUS_PAID => 'Paid',
                        Bill::STATUS_OVERDUE => 'Overdue',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('record_payment')
                    ->label('Pay')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('finance_account_id')
                            ->label('Pay From Account')
                            ->options(\App\Models\FinanceAccount::pluck('name', 'id'))
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->default(fn (Bill $record) => $record->amount - $record->paid_amount)
                            ->maxValue(fn (Bill $record) => $record->amount - $record->paid_amount)
                            ->required(),
                        Forms\Components\DatePicker::make('payment_date')
                            ->default(now())
                            ->required(),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function (Bill $record, array $data) {
                        // Create transaction
                        $transaction = \App\Models\FinanceTransaction::create([
                            'finance_account_id' => $data['finance_account_id'],
                            'type' => \App\Models\FinanceTransaction::TYPE_EXPENSE,
                            'amount' => $data['amount'],
                            'date' => $data['payment_date'],
                            'description' => 'Payment for Bill ' . ($record->bill_number ?? $record->id) . ' to ' . $record->vendor_name,
                            'reference_type' => Bill::class,
                            'reference_id' => $record->id,
                            'notes' => $data['notes'] ?? null,
                            'user_id' => Auth::id(),
                        ]);

                        // Update Bill
                        $record->paid_amount += $data['amount'];
                        if ($record->paid_amount >= $record->amount) {
                            $record->status = Bill::STATUS_PAID;
                        } else {
                            $record->status = Bill::STATUS_PARTIAL;
                        }
                        $record->save();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Payment Recorded')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Bill $record) => $record->status !== Bill::STATUS_PAID),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBills::route('/'),
            'create' => Pages\CreateBill::route('/create'),
            'edit' => Pages\EditBill::route('/{record}/edit'),
        ];
    }
}
