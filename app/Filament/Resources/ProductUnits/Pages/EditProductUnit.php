<?php

namespace App\Filament\Resources\ProductUnits\Pages;

use App\Filament\Resources\ProductUnits\ProductUnitResource;
use App\Filament\Support\PreventDeleteIfUsed;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductUnit extends EditRecord
{
    protected static string $resource = ProductUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(PreventDeleteIfUsed::guard([
                    'riwayat sewa' => fn ($record) => $record->rentalItems()->count(),
                    'catatan maintenance' => fn ($record) => $record->maintenanceRecords()->count(),
                ])),
        ];
    }
}
