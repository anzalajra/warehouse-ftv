<?php

namespace App\Filament\Clusters\Computers\Resources;

use App\Filament\Clusters\Computers\ComputersCluster;
use App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Pages\CreateComputerBooking;
use App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Pages\EditComputerBooking;
use App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Pages\ListComputerBookings;
use App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Pages\ViewComputerBooking;
use App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Schemas\ComputerBookingForm;
use App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Tables\ComputerBookingsTable;
use App\Models\ComputerBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ComputerBookingResource extends Resource
{
    protected static ?string $model = ComputerBooking::class;

    protected static ?string $cluster = ComputersCluster::class;

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $modelLabel = 'Computer Booking';

    protected static ?string $slug = 'bookings';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $recordTitleAttribute = 'booking_code';

    public static function form(Schema $schema): Schema
    {
        return ComputerBookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComputerBookingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComputerBookings::route('/'),
            'create' => CreateComputerBooking::route('/create'),
            'view' => ViewComputerBooking::route('/{record}'),
            'edit' => EditComputerBooking::route('/{record}/edit'),
        ];
    }
}
