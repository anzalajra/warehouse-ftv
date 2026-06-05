<?php

namespace App\Filament\Resources\Deliveries;

use App\Filament\Resources\Deliveries\Pages\ListDeliveries;
use App\Filament\Resources\Deliveries\Tables\DeliveriesTable;
use App\Models\Delivery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

class DeliveryResource extends Resource
{
    protected static ?string $model = Delivery::class;

    protected static ?string $recordTitleAttribute = 'delivery_number';

    // Navigation Configuration
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Rentals';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Deliveries';

    public static function table(Table $table): Table
    {
        return DeliveriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        // The deliveries board is read-only: it lists rentals with movement and
        // links into each rental's delivery hub. Surat jalan are created/edited
        // through the rental lifecycle (Pickup/Return), not here.
        return [
            'index' => ListDeliveries::route('/'),
        ];
    }
}
