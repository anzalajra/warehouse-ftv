<?php

namespace App\Filament\Clusters\Finance\Resources\JournalEntryResource\Pages;

use App\Filament\Clusters\Finance\Resources\JournalEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
            \Filament\Actions\Action::make('create')
                ->label('Create')
                ->action('create')
                ->keyBindings(['mod+s']),
            \Filament\Actions\Action::make('create_another')
                ->label('Create & create another')
                ->action('createAnother')
                ->color('gray')
                ->keyBindings(['mod+shift+s']),
            \Filament\Actions\Action::make('cancel')
                ->label('Cancel')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }
}
