<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComputers extends ListRecords
{
    protected static string $resource = ComputerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
