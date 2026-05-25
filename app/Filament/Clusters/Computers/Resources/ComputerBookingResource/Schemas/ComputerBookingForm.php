<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Schemas;

use App\Models\Computer;
use App\Models\ComputerBooking;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class ComputerBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('booking_code')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Auto-generated'),
                Select::make('user_id')
                    ->label('Customer')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('computer_id')
                    ->label('Computer')
                    ->relationship('computer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('booking_date')
                    ->required()
                    ->native(false),
                TimePicker::make('start_time')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->seconds(false)
                    ->required(),
                Select::make('status')
                    ->options([
                        ComputerBooking::STATUS_CONFIRMED => 'Confirmed',
                        ComputerBooking::STATUS_ACTIVE => 'Active',
                        ComputerBooking::STATUS_COMPLETED => 'Completed',
                        ComputerBooking::STATUS_CANCELLED => 'Cancelled',
                        ComputerBooking::STATUS_NO_SHOW => 'No Show',
                        ComputerBooking::STATUS_OVERRIDDEN => 'Overridden',
                    ])
                    ->default(ComputerBooking::STATUS_CONFIRMED)
                    ->required(),
                Textarea::make('purpose')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('admin_notes')
                    ->columnSpanFull(),
            ]);
    }
}
