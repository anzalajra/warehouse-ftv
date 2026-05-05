<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerBookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComputerBookings extends ListRecords
{
    protected static string $resource = ComputerBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
