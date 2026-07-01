<?php

namespace App\Filament\Clusters\Finance\Resources;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Resources\CurrencyResource\Pages;
use App\Models\Currency;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Mata Uang & Kurs';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Kode (ISO 3)')
                ->required()
                ->maxLength(3)
                ->extraInputAttributes(['style' => 'text-transform:uppercase']),
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('symbol')->label('Simbol')->maxLength(8),
            TextInput::make('exchange_rate')
                ->label('Kurs ke mata uang dasar')
                ->helperText('Berapa unit mata uang dasar per 1 unit mata uang ini. Mata uang dasar = 1.')
                ->numeric()
                ->required()
                ->default(1),
            Toggle::make('is_base')->label('Mata Uang Dasar'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->badge()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('symbol')->label('Simbol'),
                TextColumn::make('exchange_rate')->label('Kurs')->numeric(decimalPlaces: 6)->sortable(),
                IconColumn::make('is_base')->label('Dasar')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Currency $record) => ! $record->is_base),
            ])
            ->defaultSort('is_base', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
        ];
    }
}
