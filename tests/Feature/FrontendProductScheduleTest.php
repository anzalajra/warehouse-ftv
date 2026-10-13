<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontendProductScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_booking_block_shows_customer_name_with_status_color(): void
    {
        $customer = User::factory()->create(['name' => 'Peminjam Kamera']);
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Camera', 'slug' => 'camera']);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Test', 'slug' => 'test']);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => 'Camera',
            'slug' => 'camera',
            'daily_rate' => 100000,
        ]);
        $unitId = DB::table('product_units')->insertGetId([
            'product_id' => $productId,
            'serial_number' => 'CAM-001',
        ]);
        $rental = Rental::withoutEvents(fn () => Rental::create([
            'rental_code' => 'PRODUCT-SCHEDULE',
            'user_id' => $customer->id,
            'start_date' => '2026-10-13 09:00:00',
            'end_date' => '2026-10-15 17:00:00',
            'status' => Rental::STATUS_ACTIVE,
        ]));
        DB::table('rental_items')->insert([
            'rental_id' => $rental->id,
            'product_unit_id' => $unitId,
            'daily_rate' => 100000,
            'days' => 2,
            'subtotal' => 200000,
        ]);

        $response = $this->get('/schedule?filter=product&view_mode=month&anchor=2026-10-13');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<button[^>]*class="gr-prod-booking"[^>]*background: '.preg_quote(Rental::getStatusHexColor(Rental::STATUS_ACTIVE), '/').';[^>]*>\s*Peminjam Kamera\s*<\/button>/s',
            $response->getContent()
        );
    }
}
