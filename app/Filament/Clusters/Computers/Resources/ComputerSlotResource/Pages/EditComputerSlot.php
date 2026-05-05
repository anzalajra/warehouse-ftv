<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerSlotResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerSlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComputerSlot extends EditRecord
{
    protected static string $resource = ComputerSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
