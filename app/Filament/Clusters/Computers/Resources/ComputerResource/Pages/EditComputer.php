<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerResource;
use App\Models\Computer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Set;

class EditComputer extends EditRecord
{
    protected static string $resource = ComputerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleMaintenance')
                ->label(fn () => $this->record->status === Computer::STATUS_MAINTENANCE ? 'End Maintenance' : 'Set Maintenance')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->form(function () {
                    if ($this->record->status === Computer::STATUS_MAINTENANCE) {
                        return [];
                    }

                    return [
                        Textarea::make('reason')
                            ->required()
                            ->placeholder('Alasan masuk maintenance (e.g. Install ulang Adobe, Ganti RAM)'),
                    ];
                })
                ->action(function (array $data) {
                    if ($this->record->status === Computer::STATUS_MAINTENANCE) {
                        $this->record->status = Computer::STATUS_AVAILABLE;
                        $this->record->save();
                    } else {
                        $this->record->maintenance_reason = $data['reason'] ?? 'Maintenance';
                        $this->record->status = Computer::STATUS_MAINTENANCE;
                        $this->record->save();
                    }
                })
                ->requiresConfirmation(),
            DeleteAction::make(),
        ];
    }
}
