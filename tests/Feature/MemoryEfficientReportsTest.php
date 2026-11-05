<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\ProductUnit;
use App\Models\Rental;
use App\Models\User;
use App\Services\InventoryReportService;
use App\Services\RentalReportService;
use App\Services\TaxReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemoryEfficientReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_preserves_status_totals_dates_and_empty_days(): void
    {
        $this->rental(['status' => 'confirmed', 'subtotal' => 100.25, 'total' => 90.25]);
        $this->rental(['status' => 'completed', 'subtotal' => 20.10, 'total' => 18.10]);
        $this->rental(['status' => 'cancelled', 'created_at' => '2026-11-03 23:59:59', 'subtotal' => 50, 'total' => 50]);
        $this->rental(['created_at' => '2026-10-31 23:59:59', 'subtotal' => 999, 'total' => 999]);

        $queries = $this->queries(fn () => RentalReportService::summary('2026-11-01', '2026-11-03'));
        $report = RentalReportService::summary('2026-11-01', '2026-11-03');
        $this->assertCount(1, $queries);
        $this->assertSame(3, $report['total_count']);
        $this->assertEquals(170.35, $report['gross']);
        $this->assertEquals(158.35, $report['net']);
        $this->assertSame(2, $report['realized_count']);
        $this->assertSame([2, 0, 1], array_column($report['trend'], 'count'));
        $statuses = collect($report['by_status'])->keyBy('status');
        $this->assertSame(1, $statuses['confirmed']['count']);
        $this->assertSame(0, $statuses['expired']['count']);
        $empty = RentalReportService::summary('2027-01-01', '2027-01-01');
        $this->assertSame(0, $empty['total_count']);
        $this->assertEquals(0, $empty['net']);
    }

    public function test_late_report_limits_detail_but_summarizes_every_charge(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            $this->rental(['status' => 'completed', 'late_fee' => $i, 'returned_date' => '2026-11-04 12:00:00']);
        }
        $this->rental(['status' => 'late_pickup']);
        $report = RentalReportService::lateAndPenalty('2026-11-01', '2026-11-05');
        $this->assertSame(55, $report['count_charged']);
        $this->assertSame(1, $report['count_late_status']);
        $this->assertEquals(1540, $report['total_fee']);
        $this->assertEquals(28, $report['avg_fee']);
        $this->assertCount(50, $report['rows']);
        $this->assertEquals(55, $report['rows']->first()['late_fee']);
        $this->assertEquals(6, $report['rows']->last()['late_fee']);
        $this->assertSame('Customer', $report['rows']->first()['customer']);
    }

    public function test_discounts_and_invoice_balance_keep_the_existing_financial_rules(): void
    {
        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'number' => 'MEM-INV', 'user_id' => $this->customer()->id, 'date' => '2026-11-01',
            'total' => 1000, 'paid_amount' => 200, 'pph23_amount' => 20,
        ]));
        $this->rental([
            'status' => 'completed', 'subtotal' => 1000, 'discount' => 10, 'discount_type' => 'percent',
            'category_discount_amount' => 50, 'category_name' => 'Student',
            'daily_discount_amount' => 20, 'date_promotion_amount' => 30, 'invoice_id' => $invoice->id,
        ]);
        $this->rental(['invoice_id' => $invoice->id]);
        $discounts = RentalReportService::discounts('2026-11-01', '2026-11-05');
        $this->assertEquals([50, 20, 30, 100], array_column($discounts['layers'], 'amount'));
        $this->assertEquals(200, $discounts['total_discount']);
        $deposits = RentalReportService::depositsAndPayments('2026-11-01', '2026-11-05');
        $this->assertEquals(780, $deposits['outstanding']);
        $this->assertEquals(200, $deposits['collected']);
        $this->assertSame(2, $deposits['invoiced_count']);
    }

    public function test_stock_groups_statuses_products_and_unassigned_warehouses(): void
    {
        $product = $this->product();
        $warehouse = DB::table('warehouses')->insertGetId(['name' => 'Main']);
        foreach (['available', 'available', 'rented', 'maintenance', 'retired', 'scheduled'] as $i => $status) {
            DB::table('product_units')->insert([
                'product_id' => $product, 'serial_number' => 'MEM-'.$i, 'status' => $status,
                'warehouse_id' => $i < 3 ? $warehouse : null,
            ]);
        }
        $queries = $this->queries(fn () => InventoryReportService::stockStatus());
        $this->assertCount(3, $queries);
        $report = InventoryReportService::stockStatus();
        $this->assertSame(6, $report['total_units']);
        $this->assertSame(2, $report['totals']['available']);
        $this->assertSame(6, $report['by_product']->first()['total']);
        $this->assertSame(1, $report['by_product']->first()['scheduled']);
        $warehouses = $report['by_warehouse']->keyBy('warehouse');
        $this->assertSame(3, $warehouses['—']['total']);
        $this->assertSame(2, $warehouses['Main']['available']);
    }

    public function test_logistics_preserves_delivery_counts_and_condition_distribution(): void
    {
        $rental = $this->rental();
        $item = DB::table('rental_items')->insertGetId([
            'rental_id' => $rental->id, 'daily_rate' => 100, 'days' => 1, 'subtotal' => 100,
        ]);
        $out = DB::table('deliveries')->insertGetId([
            'rental_id' => $rental->id, 'delivery_number' => 'MEM-OUT', 'date' => '2026-11-02', 'type' => 'out',
        ]);
        $in = DB::table('deliveries')->insertGetId([
            'rental_id' => $rental->id, 'delivery_number' => 'MEM-IN', 'date' => '2026-11-02', 'type' => 'in',
        ]);
        foreach (['good', 'good', 'lost', null] as $condition) {
            DB::table('delivery_items')->insert(['delivery_id' => $in, 'rental_item_id' => $item, 'condition' => $condition]);
        }
        DB::table('delivery_items')->insert(['delivery_id' => $out, 'rental_item_id' => $item, 'condition' => 'broken']);
        $report = RentalReportService::logistics('2026-11-01', '2026-11-05');
        $this->assertSame(1, $report['out_count']);
        $this->assertSame(1, $report['in_count']);
        $this->assertSame(['good' => 2, 'lost' => 1], $report['condition_distribution']->all());
        $this->assertSame(1, $report['issues']);
        $this->assertSame(0, $report['still_out']);
    }

    public function test_inventory_memo_is_reused_within_a_scope_and_freed_between_scopes(): void
    {
        $unit = ProductUnit::withoutEvents(fn () => ProductUnit::create([
            'product_id' => $this->product(), 'serial_number' => 'MEM-BOOK',
            'purchase_price' => 1000, 'residual_value' => 100, 'accumulated_depreciation' => 200,
        ]));
        $first = InventoryReportService::unitMetrics('2026-11-01', '2026-11-05');
        $this->assertEquals(800, $first->first()['book_value']);
        $queries = $this->queries(fn () => InventoryReportService::unitMetrics('2026-11-01', '2026-11-05'));
        $this->assertSame([], $queries);

        DB::table('product_units')->where('id', $unit->id)->update(['purchase_price' => 2000]);
        $this->app->forgetScopedInstances();
        $next = InventoryReportService::unitMetrics('2026-11-01', '2026-11-05');
        $this->assertNotSame($first, $next);
        $this->assertEquals(1800, $next->first()['book_value']);
        $this->assertEquals(1800, InventoryReportService::depreciationTotals()['total_book_value']);
    }

    public function test_tax_summary_filters_drafts_cancelled_and_non_taxable_invoices(): void
    {
        foreach (['sent', 'paid', 'draft', 'cancelled'] as $i => $status) {
            DB::table('invoices')->insert([
                'number' => 'TAX-'.$i, 'user_id' => $this->customer()->id, 'date' => '2026-11-02',
                'is_taxable' => true, 'status' => $status, 'subtotal' => 100.25, 'ppn_amount' => 11.03, 'total' => 111.28,
            ]);
        }
        DB::table('invoices')->insert([
            'number' => 'NO-TAX', 'user_id' => $this->customer()->id, 'date' => '2026-11-01', 'is_taxable' => false,
        ]);
        $report = TaxReportService::generate(now()->parse('2026-11-01'), now()->parse('2026-11-05'));
        $this->assertSame(2, $report['transaction_count']);
        $this->assertEqualsWithDelta(200.50, $report['total_tax_base'], 0.001);
        $this->assertEqualsWithDelta(22.06, $report['total_ppn_payable'], 0.001);
        $empty = TaxReportService::generate(now()->parse('2027-01-01'), now()->parse('2027-01-05'));
        $this->assertEquals(0, $empty['total_sales']);
        $this->assertSame(0, $empty['transaction_count']);
    }

    private function customer(): User
    {
        return User::first() ?? User::factory()->create(['name' => 'Customer']);
    }

    private function rental(array $attributes = []): Rental
    {
        return Rental::withoutEvents(fn () => Rental::unguarded(fn () => Rental::create($attributes + [
            'rental_code' => 'MEM-'.Rental::count(), 'user_id' => $this->customer()->id,
            'status' => 'quotation', 'start_date' => '2026-11-01 12:00:00', 'end_date' => '2026-11-02 12:00:00',
            'subtotal' => 0, 'total' => 0, 'created_at' => '2026-11-01 00:00:00',
        ])));
    }

    private function product(): int
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Camera', 'slug' => 'memory-camera']);
        $brand = DB::table('brands')->insertGetId(['name' => 'Brand', 'slug' => 'memory-brand']);

        return DB::table('products')->insertGetId([
            'name' => 'Camera', 'slug' => 'memory-camera', 'category_id' => $category, 'brand_id' => $brand, 'daily_rate' => 100,
        ]);
    }

    private function queries(callable $callback): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $callback();

            return DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
    }
}
