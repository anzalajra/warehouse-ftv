<?php

namespace Tests\Feature;

use App\Models\ShootingItinerary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShootingItineraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recap_counts_distinct_shooting_start_dates_and_preserves_nim(): void
    {
        $itinerary = ShootingItinerary::create([
            'user_id' => User::factory()->create()->id,
            'production_name' => 'Film Pendek Senja',
        ]);

        $itinerary->locations()->createMany([
            ['position' => 0, 'location_name' => 'Studio A', 'start_at' => '2026-10-01 08:00:00', 'end_at' => '2026-10-01 12:00:00'],
            ['position' => 1, 'location_name' => 'Taman', 'start_at' => '2026-10-01 13:00:00', 'end_at' => '2026-10-01 18:00:00'],
            ['position' => 2, 'location_name' => 'Kampus', 'start_at' => '2026-10-02 09:00:00', 'end_at' => '2026-10-02 17:00:00'],
        ]);
        $itinerary->crew()->create([
            'position' => 0,
            'name' => 'Ayu',
            'nim' => '00123456',
            'role' => 'Sutradara',
        ]);

        $this->assertSame(2, $itinerary->fresh()->total_shooting_days);
        $this->assertSame('00123456', $itinerary->crew()->first()->nim);
    }
}
