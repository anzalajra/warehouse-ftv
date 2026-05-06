<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\Rentals\Schemas\RentalForm;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateRental extends CreateRecord
{
    protected static string $resource = RentalResource::class;

    protected array $groupedItemsData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract grouped_items before saving (not a DB column)
        $this->groupedItemsData = $data['grouped_items'] ?? [];
        unset($data['grouped_items']);
        return $data;
    }

    protected function afterCreate(): void
    {
        // Sync rental items from grouped data
        RentalForm::syncRentalItems($this->record, $this->groupedItemsData);

        // Recalculate totals from actual DB items
        $this->record->touch(); // Triggers updated observer which recalcs correctly
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCreateAnotherFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}