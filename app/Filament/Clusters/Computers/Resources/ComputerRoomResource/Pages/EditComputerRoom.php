<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerRoomResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerRoomResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComputerRoom extends EditRecord
{
    protected static string $resource = ComputerRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
