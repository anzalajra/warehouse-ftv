<?php

namespace App\Filament\Clusters\Finance\Resources\JournalEntryResource\Pages;

use App\Filament\Clusters\Finance\Resources\JournalEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJournalEntry extends EditRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        JournalEntryResource::assertBalanced($data['items'] ?? []);

        return $data;
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save changes')
                ->action('save')
                ->keyBindings(['mod+s']),
            \Filament\Actions\Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
            DeleteAction::make(),
        ];
    }
}
