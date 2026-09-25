<?php

namespace Database\Seeders;

use App\Models\Computer;
use App\Models\ComputerRoom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ComputerSeeder extends Seeder
{
    public function run(): void
    {
        $editing = ComputerRoom::firstOrCreate(['name' => 'Lab Editing 1'], ['code' => 'lab-editing-1', 'is_active' => true]);
        $color = ComputerRoom::firstOrCreate(['name' => 'Lab Color Grading'], ['code' => 'lab-color-grading', 'is_active' => true]);

        $computers = [
            [
                'room_id' => $editing->id,
                'name' => 'PC Editing 1',
                'code' => 'pc-editing-1',
                'brand' => 'Asus',
                'specs' => [
                    'CPU' => 'Intel i7-13700',
                    'RAM' => '32GB DDR5',
                    'GPU' => 'RTX 4070',
                    'Storage' => '1TB NVMe SSD',
                ],
                'notes' => 'Workstation editing video utama.',
            ],
            [
                'room_id' => $editing->id,
                'name' => 'PC Editing 2',
                'code' => 'pc-editing-2',
                'brand' => 'Asus',
                'specs' => [
                    'CPU' => 'Intel i7-13700',
                    'RAM' => '32GB DDR5',
                    'GPU' => 'RTX 4070',
                    'Storage' => '1TB NVMe SSD',
                ],
            ],
            [
                'room_id' => $color->id,
                'name' => 'Workstation Color Grading',
                'code' => 'workstation-color-grading',
                'brand' => 'Apple',
                'specs' => [
                    'CPU' => 'Apple M2 Ultra',
                    'RAM' => '64GB Unified',
                    'GPU' => '76-core GPU',
                    'Storage' => '2TB SSD',
                ],
                'notes' => 'Khusus color grading & finishing DaVinci Resolve.',
            ],
        ];

        foreach ($computers as $data) {
            Computer::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['status' => Computer::STATUS_AVAILABLE, 'checkin_slug' => Str::random(24)]),
            );
        }
    }
}
