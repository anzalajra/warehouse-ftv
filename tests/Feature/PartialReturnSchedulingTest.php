<?php

namespace Tests\Feature;

use App\Filament\Resources\Rentals\Pages\ProcessReturn;
use App\Filament\Resources\Rentals\Pages\ViewRental;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\User;
use App\Services\RentalOccupancyService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PartialReturnSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracked_kit_unit_blocks_every_parent_that_uses_it(): void
    {
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Camera', 'slug' => 'camera']);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Test', 'slug' => 'test']);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'brand_id' => $brandId,
            'name' => 'Camera', 'slug' => 'camera', 'daily_rate' => 100000,
        ]);
        $parentA = DB::table('product_units')->insertGetId(['product_id' => $productId, 'serial_number' => 'KIT-A']);
        $parentB = DB::table('product_units')->insertGetId(['product_id' => $productId, 'serial_number' => 'KIT-B']);
        $component = DB::table('product_units')->insertGetId(['product_id' => $productId, 'serial_number' => 'KIT-C']);
        $unrelated = DB::table('product_units')->insertGetId(['product_id' => $productId, 'serial_number' => 'KIT-D']);
        DB::table('unit_kits')->insert([
            ['unit_id' => $parentA, 'linked_unit_id' => $component, 'name' => 'Battery A'],
            ['unit_id' => $parentB, 'linked_unit_id' => $component, 'name' => 'Battery B'],
        ]);

        $blocked = RentalOccupancyService::blockedUnitIds([$parentA]);
        $this->assertContains($parentB, $blocked);
        $this->assertContains($component, $blocked);
        $this->assertNotContains($unrelated, $blocked);
    }

    public function test_returned_unit_is_released_and_not_recreated_on_next_checklist(): void
    {
        $user = User::factory()->create();
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Camera', 'slug' => 'camera']);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Test', 'slug' => 'test']);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'brand_id' => $brandId,
            'name' => 'Camera', 'slug' => 'camera', 'daily_rate' => 100000,
        ]);
        $unitA = DB::table('product_units')->insertGetId(['product_id' => $productId, 'serial_number' => 'PART-A']);
        $unitB = DB::table('product_units')->insertGetId(['product_id' => $productId, 'serial_number' => 'PART-B']);

        $rental = Rental::withoutEvents(fn () => Rental::create([
            'rental_code' => 'PART-TEST', 'user_id' => $user->id,
            'start_date' => now()->subDay(), 'end_date' => now()->addDays(2),
            'status' => Rental::STATUS_PARTIAL_RETURN,
        ]));
        $itemA = DB::table('rental_items')->insertGetId([
            'rental_id' => $rental->id, 'product_unit_id' => $unitA,
            'daily_rate' => 100000, 'days' => 3, 'subtotal' => 300000,
        ]);
        $itemB = DB::table('rental_items')->insertGetId([
            'rental_id' => $rental->id, 'product_unit_id' => $unitB,
            'daily_rate' => 100000, 'days' => 3, 'subtotal' => 300000,
            'effective_due_at' => now()->addDays(4), 'overtime_started_at' => now()->subHour(),
        ]);
        $completed = Delivery::withoutEvents(fn () => Delivery::create([
            'delivery_number' => 'PART-TEST-M', 'rental_id' => $rental->id,
            'type' => Delivery::TYPE_IN, 'date' => now(), 'status' => Delivery::STATUS_COMPLETED,
        ]));
        $pending = Delivery::withoutEvents(fn () => Delivery::create([
            'delivery_number' => 'PART-TEST-M2', 'rental_id' => $rental->id,
            'type' => Delivery::TYPE_IN, 'date' => now(), 'status' => Delivery::STATUS_DRAFT,
        ]));
        DB::table('delivery_items')->insert([
            ['delivery_id' => $completed->id, 'rental_item_id' => $itemA, 'is_checked' => true, 'checked_at' => now()->subHour(), 'notes' => null],
            ['delivery_id' => $pending->id, 'rental_item_id' => $itemA, 'is_checked' => false, 'checked_at' => null, 'notes' => 'Foto diterima belakangan'],
            ['delivery_id' => $pending->id, 'rental_item_id' => $itemB, 'is_checked' => false, 'checked_at' => null, 'notes' => null],
        ]);

        $rental->createDeliveries();

        $this->assertSame([$itemB], $pending->items()->whereNull('rental_item_kit_id')->pluck('rental_item_id')->all());
        $this->assertSame('Foto diterima belakangan', $completed->items()->where('rental_item_id', $itemA)->firstOrFail()->notes);
        $booked = RentalOccupancyService::bookedUnitIds(now(), now()->addDay());
        $this->assertNotContains($unitA, $booked);
        $this->assertContains($unitB, $booked);
        $available = Product::findOrFail($productId)->findAvailableUnits(now(), now()->addDay())->pluck('id')->all();
        $this->assertContains($unitA, $available);
        $this->assertNotContains($unitB, $available);
        $this->assertContains($unitA, Product::findOrFail($productId)
            ->findAvailableUnits(now()->toDateTimeString(), now()->addDay()->toDateTimeString())->pluck('id')->all());
        $this->assertTrue(ProductUnit::findOrFail($unitA)->isAvailable(now(), now()->addDay()));
        $this->assertFalse(ProductUnit::findOrFail($unitB)->isAvailable(now(), now()->addDay()));
        $this->assertCount(2, RentalOccupancyService::scheduleBlocks(RentalItem::findOrFail($itemB)));
        $candidate = Rental::withoutEvents(fn () => Rental::create([
            'rental_code' => 'PART-CANDIDATE', 'user_id' => $user->id,
            'start_date' => now(), 'end_date' => now()->addDay(),
            'status' => Rental::STATUS_QUOTATION,
        ]));
        DB::table('rental_items')->insert([
            'rental_id' => $candidate->id, 'product_unit_id' => $unitA,
            'daily_rate' => 100000, 'days' => 1, 'subtotal' => 100000,
        ]);
        $this->assertSame([], $candidate->checkAvailability());
        DB::table('rental_items')->where('rental_id', $candidate->id)->update(['product_unit_id' => $unitB]);
        $this->assertNotEmpty($candidate->fresh()->checkAvailability());
        $this->artisan('rentals:check-late')->assertExitCode(0);
        $this->assertSame(Rental::STATUS_PARTIAL_RETURN, $rental->fresh()->status);
        DB::table('rental_items')->where('id', $itemB)->update(['effective_due_at' => now()->subHour()]);
        $this->artisan('rentals:check-late')->assertExitCode(0);
        $this->assertSame(Rental::STATUS_LATE_RETURN, $rental->fresh()->status);
    }

    public function test_admin_partial_return_creates_overtime_and_rejects_empty_batch(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole('super_admin');
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Camera', 'slug' => 'camera']);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Test', 'slug' => 'test']);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'brand_id' => $brandId,
            'name' => 'Camera', 'slug' => 'camera', 'daily_rate' => 100000,
        ]);
        $unitA = DB::table('product_units')->insertGetId(['product_id' => $productId, 'serial_number' => 'FLOW-A']);
        $unitB = DB::table('product_units')->insertGetId(['product_id' => $productId, 'serial_number' => 'FLOW-B']);
        $rental = Rental::withoutEvents(fn () => Rental::create([
            'rental_code' => 'PART-FLOW', 'user_id' => $user->id,
            'start_date' => now()->subDay(), 'end_date' => now()->addDay(),
            'status' => Rental::STATUS_ACTIVE,
        ]));
        foreach ([$unitA, $unitB] as $unitId) {
            DB::table('rental_items')->insert([
                'rental_id' => $rental->id, 'product_unit_id' => $unitId,
                'daily_rate' => 100000, 'days' => 2, 'subtotal' => 200000,
            ]);
        }
        $rental->createDeliveries();
        $delivery = $rental->deliveries()->where('type', Delivery::TYPE_IN)->firstOrFail();
        $rowA = $delivery->items()->whereHas('rentalItem', fn ($q) => $q->where('product_unit_id', $unitA))->firstOrFail();

        Livewire::actingAs($user)->test(ProcessReturn::class, ['record' => $rental->id])
            ->assertOk()
            ->call('validateReturn', ['due_at' => now()->addDays(3)->format('Y-m-d H:i')])
            ->assertHasErrors('return');

        $next = Rental::withoutEvents(fn () => Rental::create([
            'rental_code' => 'NEXT-FLOW', 'user_id' => $user->id,
            'start_date' => now()->addDay(), 'end_date' => now()->addDays(2),
            'status' => Rental::STATUS_CONFIRMED,
        ]));
        DB::table('rental_items')->insert([
            'rental_id' => $next->id, 'product_unit_id' => $unitB,
            'daily_rate' => 100000, 'days' => 1, 'subtotal' => 100000,
        ]);
        $due = now()->addDays(3)->format('Y-m-d H:i');
        $component = Livewire::actingAs($user)->test(ProcessReturn::class, ['record' => $rental->id])
            ->call('quickCheck', $rowA->id)
            ->call('validateReturn', ['due_at' => $due])
            ->assertHasErrors('overlap');
        $preview = $component->instance()->previewPartial($due);
        $this->assertSame('NEXT-FLOW', $preview['conflicts'][0]['rental_code']);

        Livewire::actingAs($user)->test(ProcessReturn::class, ['record' => $rental->id])
            ->call('validateReturn', [
                'due_at' => $due,
                'override_conflicts' => true,
                'override_reason' => 'Unit pengganti disiapkan untuk rental berikutnya',
                'conflict_pairs' => array_column($preview['conflicts'], 'pair'),
            ])
            ->assertHasNoErrors();

        $this->assertSame(Rental::STATUS_PARTIAL_RETURN, $rental->fresh()->status);
        $this->assertSame(Delivery::STATUS_COMPLETED, $delivery->fresh()->status);
        $this->assertCount(2, $rental->deliveries()->where('type', Delivery::TYPE_IN)->get());
        $this->assertEquals(200000, DB::table('rental_items')->where('product_unit_id', $unitB)->value('extension_charge'));
        $this->assertEquals(600000, $rental->fresh()->subtotal);
        $this->assertEquals(600000, $rental->fresh()->invoice?->total);
        $this->get('/schedule?filter=product')->assertOk()->assertSee('Over-Time');
        $this->get('/schedule?filter=order&view_mode=day&anchor='.now()->toDateString().'&status[]=over_time')
            ->assertOk()->assertSee('loadRental('.$rental->id.', \'over_time\')', false);
        $this->getJson('/schedule/rentals/'.$rental->id.'?segment=over_time')
            ->assertOk()->assertJsonPath('status', 'Over-Time')
            ->assertJsonPath('customer', 'Terpakai')
            ->assertJsonPath('end', \Illuminate\Support\Carbon::parse($due)->format('d M Y H:i'));
        $this->getJson('/schedule/day-rentals?date='.now()->toDateString().'&status=over_time')
            ->assertOk()->assertJsonFragment(['segment' => 'over_time', 'customer' => 'Terpakai']);
        Livewire::actingAs($user)->test(\App\Filament\Pages\Schedule::class)
            ->set('filter', 'product')->assertSee('Over-Time ·');
        $this->assertNotContains($unitA, RentalOccupancyService::bookedUnitIds(now(), now()->addDay()));
        $this->assertContains($unitB, RentalOccupancyService::bookedUnitIds(now(), now()->addDay()));

        $this->assertDatabaseHas('rental_overlap_overrides', ['reason' => 'Unit pengganti disiapkan untuk rental berikutnya']);
        $currentWarnings = Livewire::actingAs($user)->test(ViewRental::class, ['record' => $rental->id])
            ->instance()->overlapWarnings();
        $nextWarnings = Livewire::actingAs($user)->test(ViewRental::class, ['record' => $next->id])
            ->instance()->overlapWarnings();
        $this->assertSame('NEXT-FLOW', $currentWarnings[0]['rental_code']);
        $this->assertSame('PART-FLOW', $nextWarnings[0]['rental_code']);

        $lastDelivery = $rental->deliveries()->where('type', Delivery::TYPE_IN)
            ->where('status', Delivery::STATUS_DRAFT)->firstOrFail();
        $remainingRow = $lastDelivery->items()->firstOrFail();
        Livewire::actingAs($user)->test(ProcessReturn::class, ['record' => $rental->id])
            ->call('quickCheck', $remainingRow->id)
            ->call('validateReturn', [])
            ->assertHasNoErrors();
        $this->assertSame(Rental::STATUS_COMPLETED, $rental->fresh()->status);
        $this->assertSame(Delivery::STATUS_COMPLETED, $lastDelivery->fresh()->status);
        $this->assertSame([], Livewire::actingAs($user)
            ->test(ViewRental::class, ['record' => $next->id])->instance()->overlapWarnings());
    }
}
