<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalQuotationExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfirmed_quotation_expires_while_confirmed_rental_becomes_late_pickup(): void
    {
        $customer = User::factory()->create();
        $quotation = $this->rental($customer, Rental::STATUS_QUOTATION, now()->subMinute());
        $confirmed = $this->rental($customer, Rental::STATUS_CONFIRMED, now()->subMinute());

        $this->artisan('rentals:check-late')->assertExitCode(0);

        $this->assertSame(Rental::STATUS_EXPIRED, $quotation->fresh()->status);
        $this->assertSame(Rental::STATUS_LATE_PICKUP, $confirmed->fresh()->status);
    }

    public function test_expired_quotation_requires_new_future_dates_before_reopening(): void
    {
        $rental = $this->rental(User::factory()->create(), Rental::STATUS_EXPIRED, now()->subDay());

        try {
            $rental->reopenExpiredQuotation();
            $this->fail('Reopening with a past pickup date should fail.');
        } catch (\DomainException $e) {
            $this->assertSame(Rental::STATUS_EXPIRED, $rental->fresh()->status);
        }

        $rental->update([
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
        ]);
        $rental->reopenExpiredQuotation();

        $this->assertSame(Rental::STATUS_QUOTATION, $rental->fresh()->status);
    }

    private function rental(User $customer, string $status, \Carbon\Carbon $start): Rental
    {
        return Rental::withoutEvents(fn () => Rental::create([
            'rental_code' => 'TEST-'.\Illuminate\Support\Str::uuid(),
            'user_id' => $customer->id,
            'start_date' => $start,
            'end_date' => $start->copy()->addDay(),
            'status' => $status,
            'subtotal' => 0,
            'total' => 0,
        ]));
    }
}
