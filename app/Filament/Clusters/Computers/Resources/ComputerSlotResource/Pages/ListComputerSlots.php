<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerSlotResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComputerSlots extends ListRecords
{
    protected static string $resource = ComputerSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
