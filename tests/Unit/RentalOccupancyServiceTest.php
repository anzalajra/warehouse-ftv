<?php

namespace Tests\Unit;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Services\RentalOccupancyService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RentalOccupancyServiceTest extends TestCase
{
    public function test_confirmed_partial_return_releases_only_returned_unit(): void
    {
        $start = Carbon::parse('2026-09-20 10:00');
        $originalDue = Carbon::parse('2026-09-30 10:00');
        $returnedAt = Carbon::parse('2026-09-25 10:00');
        $rental = new Rental(['status' => Rental::STATUS_PARTIAL_RETURN, 'start_date' => $start, 'end_date' => $originalDue]);

        $completed = new Delivery(['type' => Delivery::TYPE_IN, 'status' => Delivery::STATUS_COMPLETED]);
        $returned = new RentalItem(['product_unit_id' => 1]);
        $row = new DeliveryItem(['is_checked' => true, 'checked_at' => $returnedAt]);
        $row->setRelation('delivery', $completed);
        $returned->setRelation('rental', $rental);
        $returned->setRelation('deliveryItems', collect([$row]));

        $remaining = new RentalItem([
            'product_unit_id' => 2,
            'overtime_started_at' => $returnedAt,
            'effective_due_at' => Carbon::parse('2026-10-03 10:00'),
        ]);
        $remaining->setRelation('rental', $rental);
        $remaining->setRelation('deliveryItems', collect());

        $newStart = Carbon::parse('2026-09-26 10:00');
        $newEnd = Carbon::parse('2026-09-27 10:00');
        $this->assertFalse(RentalOccupancyService::overlaps($returned, $newStart, $newEnd));
        $this->assertTrue(RentalOccupancyService::overlaps($remaining, $newStart, $newEnd));
        $blocks = RentalOccupancyService::scheduleBlocks($remaining);
        $this->assertCount(1, $blocks);
        $this->assertSame(Rental::STATUS_PARTIAL_RETURN, $blocks[0]['status']);
        $this->assertTrue($blocks[0]['start']->equalTo($start));
        $this->assertTrue($blocks[0]['end']->equalTo($originalDue));
    }

    public function test_draft_check_does_not_release_unit(): void
    {
        $rental = new Rental([
            'status' => Rental::STATUS_PARTIAL_RETURN,
            'start_date' => Carbon::parse('2026-09-20'),
            'end_date' => Carbon::parse('2026-09-30'),
        ]);
        $draft = new Delivery(['type' => Delivery::TYPE_IN, 'status' => Delivery::STATUS_DRAFT]);
        $item = new RentalItem(['product_unit_id' => 1]);
        $row = new DeliveryItem(['is_checked' => true, 'checked_at' => Carbon::parse('2026-09-25')]);
        $row->setRelation('delivery', $draft);
        $item->setRelation('rental', $rental);
        $item->setRelation('deliveryItems', collect([$row]));

        $this->assertNull(RentalOccupancyService::returnedAt($item));
        $this->assertTrue(RentalOccupancyService::overlaps($item, Carbon::parse('2026-09-26'), Carbon::parse('2026-09-27')));
    }

    public function test_unreturned_item_remains_occupied_after_its_due_time(): void
    {
        $start = now()->subDays(3);
        $due = now()->subDay();
        $visibleEnd = now()->addDay();
        $rental = new Rental([
            'status' => Rental::STATUS_LATE_RETURN,
            'start_date' => $start,
            'end_date' => $due,
        ]);
        $item = new RentalItem(['product_unit_id' => 1]);
        $item->setRelation('rental', $rental);
        $item->setRelation('deliveryItems', collect());

        $this->assertNull(RentalOccupancyService::occupiedUntil($item));
        $this->assertTrue(RentalOccupancyService::overlaps($item, now(), $visibleEnd));
        $blocks = RentalOccupancyService::scheduleBlocks($item);
        $this->assertCount(1, $blocks);
        $this->assertSame(Rental::STATUS_LATE_RETURN, $blocks[0]['status']);
        $this->assertSame($start->toDateTimeString(), $blocks[0]['start']->toDateTimeString());
        $this->assertSame($due->toDateTimeString(), $blocks[0]['end']->toDateTimeString());
        $this->assertSame($rental, RentalOccupancyService::overdueHolder($item));
    }

    public function test_returned_or_extended_item_has_no_overdue_holder(): void
    {
        $rental = new Rental([
            'status' => Rental::STATUS_PARTIAL_RETURN,
            'start_date' => now()->subDays(3),
            'end_date' => now()->subDay(),
        ]);
        $item = new RentalItem(['product_unit_id' => 1, 'effective_due_at' => now()->addDay()]);
        $item->setRelation('rental', $rental);
        $item->setRelation('deliveryItems', collect());
        $this->assertNull(RentalOccupancyService::overdueHolder($item));

        $item->effective_due_at = now()->subHour();
        $completed = new Delivery(['type' => Delivery::TYPE_IN, 'status' => Delivery::STATUS_COMPLETED]);
        $row = new DeliveryItem(['is_checked' => true, 'checked_at' => now()->subHour()]);
        $row->setRelation('delivery', $completed);
        $item->setRelation('deliveryItems', collect([$row]));
        $this->assertNull(RentalOccupancyService::overdueHolder($item));
    }
}
