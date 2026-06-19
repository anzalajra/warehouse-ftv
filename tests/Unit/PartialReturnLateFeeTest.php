<?php

namespace Tests\Unit;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Rental;
use App\Models\RentalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Late fee must be scoped PER ITEM to each item's own return window. In a partial
 * return some items come back on time while others are kept out and returned late;
 * the on-time items must not be charged for the days the late items were withheld.
 *
 * Built entirely in-memory (relations set via setRelation) so it exercises
 * Rental::calculateOverdueFee()'s per-item logic without the full
 * Product/Unit/Delivery persistence + observers. RefreshDatabase only provides an
 * empty settings table so the late-fee mode resolves to its default
 * (full_daily_rate, which is a per-item mode).
 */
class PartialReturnLateFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_late_fee_only_charges_each_item_for_its_own_overdue_window(): void
    {
        $now = Carbon::parse('2026-06-19 12:00:00');
        Carbon::setTestNow($now);

        $endDate = $now->copy()->subDays(5); // rental was due 5 days ago

        $inDelivery = new Delivery(['type' => Delivery::TYPE_IN]);

        // Item A — returned ON TIME (checked in at end_date, e.g. an earlier partial batch).
        $itemA = new RentalItem(['product_unit_id' => 1, 'daily_rate' => 100000, 'days' => 1]);
        $diA = new DeliveryItem(['is_checked' => true]);
        $diA->checked_at = $endDate;       // frozen at its real (on-time) return moment
        $diA->rental_item_kit_id = null;
        $diA->setRelation('delivery', $inDelivery);
        $itemA->setRelation('deliveryItems', collect([$diA]));
        $itemA->setRelation('productUnit', null); // no product override → uses daily_rate

        // Item B — STILL OUT (not checked) → accrues up to now() = 5 days late.
        $itemB = new RentalItem(['product_unit_id' => 2, 'daily_rate' => 100000, 'days' => 1]);
        $diB = new DeliveryItem(['is_checked' => false]);
        $diB->rental_item_kit_id = null;
        $diB->setRelation('delivery', $inDelivery);
        $itemB->setRelation('deliveryItems', collect([$diB]));
        $itemB->setRelation('productUnit', null);

        $rental = new Rental(['end_date' => $endDate, 'status' => Rental::STATUS_LATE_RETURN]);
        $rental->setRelation('items', collect([$itemA, $itemB]));

        $fee = $rental->calculateOverdueFee();

        // full_daily_rate: only B is late → 100000 × 1 unit × 5 days = 500000.
        // The on-time item A contributes nothing. The old rental-wide calc would
        // have charged both items for 5 days (= 1,000,000).
        $this->assertSame(500000.0, $fee);
        $this->assertNotSame(1000000.0, $fee);
    }

    public function test_no_late_fee_when_all_items_returned_on_time(): void
    {
        $now = Carbon::parse('2026-06-19 12:00:00');
        Carbon::setTestNow($now);

        $endDate = $now->copy()->subDays(5);

        $inDelivery = new Delivery(['type' => Delivery::TYPE_IN]);

        $item = new RentalItem(['product_unit_id' => 1, 'daily_rate' => 100000, 'days' => 1]);
        $di = new DeliveryItem(['is_checked' => true]);
        $di->checked_at = $endDate; // returned exactly on time
        $di->rental_item_kit_id = null;
        $di->setRelation('delivery', $inDelivery);
        $item->setRelation('deliveryItems', collect([$di]));
        $item->setRelation('productUnit', null);

        $rental = new Rental(['end_date' => $endDate, 'status' => Rental::STATUS_PARTIAL_RETURN]);
        $rental->setRelation('items', collect([$item]));

        $this->assertSame(0.0, $rental->calculateOverdueFee());
    }
}
