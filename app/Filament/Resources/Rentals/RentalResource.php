<?php

namespace App\Filament\Resources\Rentals;

use App\Filament\Resources\Rentals\Pages\CreateRental;
use App\Filament\Resources\Rentals\Pages\EditRental;
use App\Filament\Resources\Rentals\Pages\ListRentals;
use App\Filament\Resources\Rentals\Pages\PickupOperation;
use App\Filament\Resources\Rentals\Pages\ProcessReturn;
use App\Filament\Resources\Rentals\Pages\RentalDocuments;
use App\Filament\Resources\Rentals\Pages\RentalKanbanBoard;
use App\Filament\Resources\Rentals\Pages\ViewRental;
use App\Filament\Resources\Rentals\Schemas\RentalForm;
use App\Filament\Resources\Rentals\Tables\RentalsTable;
use App\Models\Rental;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RentalResource extends Resource
{
    protected static ?string $model = Rental::class;

    protected static ?string $recordTitleAttribute = 'rental_code';

    // Navigation Configuration
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    
    protected static string|UnitEnum|null $navigationGroup = 'Rentals';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $navigationLabel = 'Rentals';

    public static function getNavigationBadge(): ?string
    {
        $quotation = static::getModel()::where('status', Rental::STATUS_QUOTATION)->count();
        $late = static::getModel()::whereIn('status', [Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN])->count();

        if ($quotation === 0 && $late === 0) {
            return null;
        }

        if ($late > 0) {
             return $quotation > 0 ? "{$quotation} | {$late}" : (string) $late;
        }

        return (string) $quotation;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $quotation = static::getModel()::where('status', Rental::STATUS_QUOTATION)->count();
        $late = static::getModel()::whereIn('status', [Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN])->count();
        
        $parts = [];
        if ($quotation > 0) $parts[] = "{$quotation} Quotation";
        if ($late > 0) $parts[] = "{$late} Late Rental";
        
        return implode(' & ', $parts);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::whereIn('status', [Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN])->exists() 
            ? 'danger' 
            : 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return RentalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentalsTable::configure($table);
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
            'index' => ListRentals::route('/'),
            'create' => CreateRental::route('/create'),
            'edit' => EditRental::route('/{record}/edit'),
            'pickup' => PickupOperation::route('/{record}/pickup'),
            'return' => ProcessReturn::route('/{record}/return'),
            'delivery' => RentalDocuments::route('/{record}/delivery'),
            'view' => ViewRental::route('/{record}/view'),
            'kanban' => RentalKanbanBoard::route('/kanban'),
        ];
    }
}