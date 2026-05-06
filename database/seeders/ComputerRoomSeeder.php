<?php

namespace Database\Seeders;

use App\Models\ComputerRoom;
use Illuminate\Database\Seeder;

class ComputerRoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            [
                'name' => 'Lab Editing 1',
                'description' => 'Ruang editing video utama lantai 2.',
            ],
            [
                'name' => 'Lab Color Grading',
                'description' => 'Ruangan khusus color grading & finishing.',
            ],
        ];

        foreach ($rooms as $data) {
            ComputerRoom::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['is_active' => true]),
            );
        }
    }
}
