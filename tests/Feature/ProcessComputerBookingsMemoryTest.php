<?php

namespace Tests\Feature;

use App\Models\ComputerBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessComputerBookingsMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_booking_is_processed_when_status_changes_cross_a_batch_boundary(): void
    {
        $this->travelTo(now()->setTime(14, 0));
        $user = User::factory()->create();
        $computer = DB::table('computers')->insertGetId(['name' => 'Computer', 'code' => 'MEM-PC']);
        $rows = [];
        foreach (['no_show', 'active', 'completed'] as $target) {
            for ($i = 0; $i < 205; $i++) {
                $rows[] = [
                    'booking_code' => $target.'-'.$i, 'user_id' => $user->id, 'computer_id' => $computer,
                    'booking_date' => now()->toDateString(), 'start_time' => '10:00',
                    'end_time' => $target === 'active' ? '16:00' : '12:00', 'purpose' => 'Test',
                    'status' => $target === 'completed' ? 'active' : 'confirmed',
                    'checked_in_at' => $target === 'no_show' ? null : now()->toDateTimeString(),
                ];
            }
        }
        foreach (array_chunk($rows, 100) as $batch) {
            DB::table('computer_bookings')->insert($batch);
        }
        $updates = 0;
        ComputerBooking::updated(static function () use (&$updates) {
            $updates++;
        });

        $this->artisan('computer-bookings:process')
            ->expectsOutput('Processed: no_show=205, activated=205, completed=205')
            ->assertSuccessful();

        $this->assertSame(615, $updates);
        foreach (['no_show', 'active', 'completed'] as $status) {
            $this->assertSame(205, ComputerBooking::where('status', $status)->count());
        }
    }
}
