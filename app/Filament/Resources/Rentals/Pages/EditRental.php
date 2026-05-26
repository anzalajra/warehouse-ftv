<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Rental;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class EditRental extends Page
{
    protected static string $resource = RentalResource::class;

    public ?Rental $rental = null;

    public function getView(): string
    {
        return 'filament.rentals.editor';
    }

    public function mount(int|string $record): void
    {
        $this->rental = Rental::with('items.productUnit')->findOrFail($record);

        if (! $this->rental->canBeEdited()) {
            Notification::make()
                ->title('Cannot edit this rental')
                ->body('This rental is currently active and cannot be edited.')
                ->danger()
                ->send();

            $this->redirect(RentalResource::getUrl('index'));
        }
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
