<?php

namespace Database\Seeders;

use App\Models\ComputerBookingSlot;
use Illuminate\Database\Seeder;

class ComputerBookingSlotSeeder extends Seeder
{
    public function run(): void
    {
        // Senin (1) – Jumat (5), 4 slot per hari.
        $slots = [
            ['08:00', '10:00'],
            ['10:00', '12:00'],
            ['13:00', '15:00'],
            ['15:00', '17:00'],
        ];

        for ($day = 1; $day <= 5; $day++) {
            foreach ($slots as [$start, $end]) {
                ComputerBookingSlot::firstOrCreate(
                    [
                        'day_of_week' => $day,
                        'start_time' => $start,
                        'end_time' => $end,
                    ],
                    ['is_active' => true],
                );
            }
        }
    }
}
