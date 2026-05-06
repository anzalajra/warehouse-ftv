<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Filament\Resources\Rentals\Schemas\RentalForm;
use App\Models\Rental;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRental extends EditRecord
{
    protected static string $resource = RentalResource::class;

    protected array $groupedItemsData = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Check if rental can be edited
        if (!$this->record->canBeEdited()) {
            Notification::make()
                ->title('Cannot edit this rental')
                ->body('This rental is currently active and cannot be edited.')
                ->danger()
                ->send();

            $this->redirect(RentalResource::getUrl('index'));
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load rental items and group them for the form
        $rental = Rental::with('items.productUnit')->findOrFail($data['id']);
        $data['grouped_items'] = RentalForm::groupItemsForForm($rental->items);
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract grouped_items before saving (not a DB column)
        $this->groupedItemsData = $data['grouped_items'] ?? [];
        unset($data['grouped_items']);
        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // Save quietly to prevent Rental::saved event from triggering
        // refreshUnitStatuses() — syncRentalItems() handles this more efficiently.
        $record->fill($data);
        $record->saveQuietly();

        return $record;
    }

    protected function afterSave(): void
    {
        // Sync rental items from grouped data
        RentalForm::syncRentalItems($this->record, $this->groupedItemsData);
        $this->record->touch(); // Recalculate totals via updated observer
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => $this->record->canBeDeleted()),
        ];
    }
}
