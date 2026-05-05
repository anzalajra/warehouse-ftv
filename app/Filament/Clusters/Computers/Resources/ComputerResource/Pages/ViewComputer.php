<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewComputer extends ViewRecord
{
    protected static string $resource = ComputerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
