<?php

namespace Database\Seeders;

use App\Models\Computer;
use Illuminate\Database\Seeder;

class ComputerSeeder extends Seeder
{
    public function run(): void
    {
        $computers = [
            [
                'name' => 'PC Editing 1',
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
                'name' => 'PC Editing 2',
                'brand' => 'Asus',
                'specs' => [
                    'CPU' => 'Intel i7-13700',
                    'RAM' => '32GB DDR5',
                    'GPU' => 'RTX 4070',
                    'Storage' => '1TB NVMe SSD',
                ],
            ],
            [
                'name' => 'Workstation Color Grading',
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
                array_merge($data, ['status' => Computer::STATUS_AVAILABLE]),
            );
        }
    }
}
