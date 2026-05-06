<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerSlotResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ComputerSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('day_of_week')
                    ->label('Day')
                    ->options([
                        0 => 'Sunday',
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                    ])
                    ->required(),
                TimePicker::make('start_time')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->seconds(false)
                    ->required(),
                Toggle::make('is_active')
                    ->default(true),
                Toggle::make('is_night')
                    ->label('Jam Malam')
                    ->helperText('Jika aktif, booking di slot ini menampilkan banner perizinan menginap.')
                    ->default(false),
            ]);
    }
}
