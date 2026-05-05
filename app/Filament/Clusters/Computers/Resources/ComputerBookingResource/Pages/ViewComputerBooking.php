<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerBookingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewComputerBooking extends ViewRecord
{
    protected static string $resource = ComputerBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
