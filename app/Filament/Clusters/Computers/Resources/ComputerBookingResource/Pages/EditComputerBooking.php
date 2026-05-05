<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerBookingResource;
use App\Services\ComputerValidationService;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditComputerBooking extends EditRecord
{
    protected static string $resource = ComputerBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $date = Carbon::parse($data['booking_date']);
        $start = substr($data['start_time'], 0, 5);
        $end = substr($data['end_time'], 0, 5);

        $available = ComputerValidationService::checkComputerAvailability(
            (int) $data['computer_id'],
            $date,
            $start,
            $end,
            (int) $this->record->id,
        );

        if (! $available) {
            throw ValidationException::withMessages([
                'data.computer_id' => 'Komputer tidak tersedia di slot tersebut (bentrok dengan booking lain atau maintenance).',
            ]);
        }

        return $data;
    }
}
