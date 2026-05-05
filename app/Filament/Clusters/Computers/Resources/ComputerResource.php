<?php

namespace App\Filament\Clusters\Computers\Resources;

use App\Filament\Clusters\Computers\ComputersCluster;
use App\Filament\Clusters\Computers\Resources\ComputerResource\Pages\CreateComputer;
use App\Filament\Clusters\Computers\Resources\ComputerResource\Pages\EditComputer;
use App\Filament\Clusters\Computers\Resources\ComputerResource\Pages\ListComputers;
use App\Filament\Clusters\Computers\Resources\ComputerResource\Pages\ViewComputer;
use App\Filament\Clusters\Computers\Resources\ComputerResource\Schemas\ComputerForm;
use App\Filament\Clusters\Computers\Resources\ComputerResource\Tables\ComputersTable;
use App\Models\Computer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ComputerResource extends Resource
{
    protected static ?string $model = Computer::class;

    protected static ?string $cluster = ComputersCluster::class;

    protected static ?string $navigationLabel = 'Computers';

    protected static ?string $modelLabel = 'Computer';

    protected static ?string $slug = 'computers';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ComputerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComputersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComputers::route('/'),
            'create' => CreateComputer::route('/create'),
            'view' => ViewComputer::route('/{record}'),
            'edit' => EditComputer::route('/{record}/edit'),
        ];
    }
}
