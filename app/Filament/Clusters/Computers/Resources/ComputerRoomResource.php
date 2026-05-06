<?php

namespace App\Filament\Clusters\Computers\Resources;

use App\Filament\Clusters\Computers\ComputersCluster;
use App\Filament\Clusters\Computers\Resources\ComputerRoomResource\Pages\CreateComputerRoom;
use App\Filament\Clusters\Computers\Resources\ComputerRoomResource\Pages\EditComputerRoom;
use App\Filament\Clusters\Computers\Resources\ComputerRoomResource\Pages\ListComputerRooms;
use App\Filament\Clusters\Computers\Resources\ComputerRoomResource\Schemas\ComputerRoomForm;
use App\Filament\Clusters\Computers\Resources\ComputerRoomResource\Tables\ComputerRoomsTable;
use App\Models\ComputerRoom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ComputerRoomResource extends Resource
{
    protected static ?string $model = ComputerRoom::class;

    protected static ?string $cluster = ComputersCluster::class;

    protected static ?string $navigationLabel = 'Rooms';

    protected static ?string $modelLabel = 'Room';

    protected static ?string $slug = 'rooms';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ComputerRoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComputerRoomsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComputerRooms::route('/'),
            'create' => CreateComputerRoom::route('/create'),
            'edit' => EditComputerRoom::route('/{record}/edit'),
        ];
    }
}
