<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerBookingResource;
use App\Services\ComputerValidationService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateComputerBooking extends CreateRecord
{
    protected static string $resource = ComputerBookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $date = Carbon::parse($data['booking_date']);
        $start = substr($data['start_time'], 0, 5);
        $end = substr($data['end_time'], 0, 5);

        $available = ComputerValidationService::checkComputerAvailability(
            (int) $data['computer_id'],
            $date,
            $start,
            $end,
        );

        if (! $available) {
            throw ValidationException::withMessages([
                'data.computer_id' => 'Komputer tidak tersedia di slot tersebut (bentrok dengan booking lain atau maintenance).',
            ]);
        }

        return $data;
    }
}
