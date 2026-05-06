<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerRoomResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerRoomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComputerRooms extends ListRecords
{
    protected static string $resource = ComputerRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
