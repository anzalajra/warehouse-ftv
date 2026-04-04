<?php

namespace App\Filament\Clusters\Finance\Resources;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\FinanceAccount;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use BackedEnum;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $cluster = FinanceCluster::class;

    protected static ?string $navigationLabel = 'Operational Expenses';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expense Details')
                    ->schema([
                        DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        Select::make('finance_account_id')
                            ->label('Paid From Account')
                            ->options(FinanceAccount::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('Rp'),
                        Select::make('category')
                            ->options([
                                'Operational' => 'Operational',
                                'Utilities' => 'Utilities',
                                'Salary' => 'Salary',
                                'Maintenance' => 'Maintenance',
                                'Fuel' => 'Fuel',
                                'Marketing' => 'Marketing',
                                'Other' => 'Other',
                            ])
                            ->required()
                            ->searchable()
                            ->preload(),
                        Textarea::make('description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        FileUpload::make('proof_document')
                            ->disk('local')
                            ->directory('expenses')
                            ->visibility('private')
                            ->image()
                            ->imageEditor(),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('user_id')
                            ->default(fn () => Auth::id()),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('category')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                TextColumn::make('description')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Recorded By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Operational' => 'Operational',
                        'Utilities' => 'Utilities',
                        'Salary' => 'Salary',
                        'Maintenance' => 'Maintenance',
                        'Fuel' => 'Fuel',
                        'Marketing' => 'Marketing',
                        'Other' => 'Other',
                    ]),
                Tables\Filters\SelectFilter::make('finance_account_id')
                    ->label('Account')
                    ->relationship('account', 'name'),
                Tables\Filters\Filter::make('date')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
