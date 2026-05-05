<?php

namespace App\Filament\Clusters\Computers\Resources;

use App\Filament\Clusters\Computers\ComputersCluster;
use App\Filament\Clusters\Computers\Resources\ComputerSlotResource\Pages\CreateComputerSlot;
use App\Filament\Clusters\Computers\Resources\ComputerSlotResource\Pages\EditComputerSlot;
use App\Filament\Clusters\Computers\Resources\ComputerSlotResource\Pages\ListComputerSlots;
use App\Filament\Clusters\Computers\Resources\ComputerSlotResource\Schemas\ComputerSlotForm;
use App\Filament\Clusters\Computers\Resources\ComputerSlotResource\Tables\ComputerSlotsTable;
use App\Models\ComputerBookingSlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ComputerSlotResource extends Resource
{
    protected static ?string $model = ComputerBookingSlot::class;

    protected static ?string $cluster = ComputersCluster::class;

    protected static ?string $navigationLabel = 'Slot Management';

    protected static ?string $modelLabel = 'Booking Slot';

    protected static ?string $slug = 'slots';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    public static function form(Schema $schema): Schema
    {
        return ComputerSlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComputerSlotsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComputerSlots::route('/'),
            'create' => CreateComputerSlot::route('/create'),
            'edit' => EditComputerSlot::route('/{record}/edit'),
        ];
    }
}
