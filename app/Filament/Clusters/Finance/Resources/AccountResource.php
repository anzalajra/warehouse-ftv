<?php

namespace App\Filament\Clusters\Finance\Resources;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Resources\AccountResource\Pages;
use App\Filament\Support\PreventDeleteIfUsed;
use App\Models\Account;
use App\Models\CategoryMapping;
use App\Models\Setting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use BackedEnum;

use App\Filament\Clusters\Finance\Resources\AccountResource\RelationManagers\JournalItemsRelationManager;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $cluster = FinanceCluster::class;

    protected static ?string $navigationLabel = 'Chart of Accounts';
    
    protected static ?string $modelLabel = 'Account';
    
    protected static ?string $pluralModelLabel = 'Chart of Accounts';
    
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return Setting::get('finance_mode', 'advanced') === 'advanced';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Account Information')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->label('Account Code')
                            ->placeholder('1-1100')
                            ->columnSpan(1),
                        TextInput::make('name')
                            ->required()
                            ->label('Account Name')
                            ->columnSpan(2),
                        Select::make('parent_id')
                            ->relationship('parent', 'name', fn ($query) => $query->orderBy('code'))
                            ->searchable()
                            ->preload()
                            ->label('Parent Account')
                            ->columnSpan(1),
                        Select::make('type')
                            ->options([
                                'asset' => 'Asset (Harta)',
                                'liability' => 'Liability (Kewajiban)',
                                'equity' => 'Equity (Modal)',
                                'revenue' => 'Revenue (Pendapatan)',
                                'expense' => 'Expense (Beban)',
                            ])
                            ->required()
                            ->live()
                            ->columnSpan(1),
                        TextInput::make('subtype')
                            ->label('Sub Type')
                            ->placeholder('current_asset')
                            ->columnSpan(1),
                        \Filament\Forms\Components\Checkbox::make('is_sub_account')
                             ->label('Is Sub Account')
                             ->inline(false)
                             ->columnSpan(1),
                        TextInput::make('description')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('parent.name')->label('Parent')->sortable()->toggleable(),
                TextColumn::make('type')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'asset' => 'info',
                        'liability' => 'warning',
                        'equity' => 'success',
                        'revenue' => 'success',
                        'expense' => 'danger',
                    }),
                TextColumn::make('balance')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('subtype')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_sub_account')
                    ->boolean()
                    ->label('Sub')
                    ->sortable(),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'asset' => 'Asset',
                        'liability' => 'Liability',
                        'equity' => 'Equity',
                        'revenue' => 'Revenue',
                        'expense' => 'Expense',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->before(PreventDeleteIfUsed::guard([
                        'transaksi jurnal' => fn ($record) => $record->items()->count(),
                        'mapping kategori' => fn ($record) => CategoryMapping::where('account_id', $record->id)->count(),
                        'sub-account' => fn ($record) => $record->children()->count(),
                    ])),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [
            JournalItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
