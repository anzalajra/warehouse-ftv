<?php

// Isolated synthetic benchmark. No application data or stored configuration is changed.
// php scripts/benchmark-memory.php summary 5000 [/path/to/baseline/services]
// Scenarios: summary, stock, tax, late, discounts, occupancy.

use App\Services\InventoryReportService;
use App\Services\RentalOccupancyService;
use App\Services\RentalReportService;
use App\Services\TaxReportService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$scenario = $argv[1] ?? 'summary';
$count = filter_var($argv[2] ?? 5000, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
if (! in_array($scenario, ['summary', 'stock', 'tax', 'late', 'discounts', 'occupancy'], true) || $count === false) {
    fwrite(STDERR, "Usage: php scripts/benchmark-memory.php <summary|stock|tax|late|discounts|occupancy> <1..100000> [baseline-services-directory]\n");
    exit(1);
}

foreach ([
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/warehouse-memory-no-cache/config.php',
    'APP_ROUTES_CACHE' => sys_get_temp_dir().'/warehouse-memory-no-cache/routes.php',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
// Ignore a production config cache, without clearing or overwriting it.
$app->useEnvironmentPath(sys_get_temp_dir().'/warehouse-memory-no-env');
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
DB::purge('sqlite');
Artisan::call('migrate', ['--force' => true]);
Carbon::setTestNow(Carbon::parse('2026-11-05 14:00:00', 'Asia/Jakarta'));

if (isset($argv[3])) {
    foreach (['RentalReportService', 'InventoryReportService', 'TaxReportService', 'RentalOccupancyService'] as $service) {
        $file = rtrim($argv[3], '/').'/'.$service.'.php';
        if (is_file($file)) {
            require $file;
        }
    }
}

$userId = DB::table('users')->insertGetId([
    'name' => 'Benchmark', 'email' => 'benchmark@example.test', 'password' => 'synthetic-benchmark-unused',
]);
$category = DB::table('categories')->insertGetId(['name' => 'Camera', 'slug' => 'bench']);
$brand = DB::table('brands')->insertGetId(['name' => 'Brand', 'slug' => 'bench']);
$product = DB::table('products')->insertGetId(['category_id' => $category, 'brand_id' => $brand, 'name' => 'Camera', 'slug' => 'bench', 'daily_rate' => 100]);
$statuses = ['quotation', 'confirmed', 'active', 'completed', 'cancelled', 'late_pickup', 'late_return', 'partial_return', 'expired'];
$unitStatuses = ['available', 'scheduled', 'rented', 'maintenance', 'retired'];
$notes = str_repeat('N', 2048);
$snapshot = str_repeat('S', 8192); // Stored payload size; reports must not decrypt or fetch it.
for ($offset = 0; $offset < $count; $offset += 100) {
    $rentals = $units = $invoices = $items = [];
    for ($i = $offset; $i < min($count, $offset + 100); $i++) {
        $date = sprintf('2026-11-%02d 12:00:00', 1 + ($i % 28));
        $rentals[] = [
            'id' => $i + 1, 'rental_code' => 'BENCH-'.$i, 'user_id' => $userId,
            'status' => $statuses[$i % count($statuses)], 'created_at' => $date,
            'start_date' => '2026-11-01 12:00:00', 'end_date' => '2026-11-02 12:00:00',
            'subtotal' => 100.25, 'total' => 90.25, 'late_fee' => $i % 100,
            'discount' => 10, 'discount_type' => 'percent', 'category_discount_amount' => 5,
            'notes' => $notes, 'mufise_call_sheet_html' => $snapshot,
        ];
        $units[] = ['id' => $i + 1, 'product_id' => $product, 'serial_number' => 'BENCH-'.$i, 'status' => $unitStatuses[$i % count($unitStatuses)], 'notes' => $notes];
        $invoices[] = ['number' => 'BENCH-'.$i, 'user_id' => $userId, 'date' => '2026-11-02', 'status' => 'sent', 'is_taxable' => true, 'subtotal' => 100.25, 'ppn_amount' => 11.03, 'total' => 111.28, 'notes' => $notes];
        $items[] = ['rental_id' => $i + 1, 'product_unit_id' => $i + 1, 'product_id' => $product, 'daily_rate' => 100.25, 'days' => 1, 'subtotal' => 100.25];
    }
    DB::table('rentals')->insert($rentals);
    DB::table('product_units')->insert($units);
    DB::table('invoices')->insert($invoices);
    DB::table('rental_items')->insert($items);
}
unset($rentals, $units, $invoices, $items, $notes, $snapshot);
gc_collect_cycles();
DB::disableQueryLog();
$queries = 0;
DB::listen(function () use (&$queries) {
    $queries++;
});
$startMemory = memory_get_usage();
memory_reset_peak_usage();
$start = hrtime(true);
$result = match ($scenario) {
    'summary' => RentalReportService::summary('2026-11-01', '2026-11-30'),
    'stock' => InventoryReportService::stockStatus(),
    'tax' => TaxReportService::generate(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')),
    'late' => RentalReportService::lateAndPenalty('2026-11-01', '2026-11-30'),
    'discounts' => RentalReportService::discounts('2026-11-01', '2026-11-30'),
    'occupancy' => RentalOccupancyService::bookedUnitIds(Carbon::parse('2026-11-05'), Carbon::parse('2026-11-06')),
};
$elapsed = (hrtime(true) - $start) / 1e6;
$peak = memory_get_peak_usage() - $startMemory;
if (getenv('WAREHOUSE_BENCHMARK_SHOW_RESULT')) {
    fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION).PHP_EOL);
}
echo json_encode([
    'scenario' => $scenario, 'rows_per_table' => $count, 'elapsed_ms' => round($elapsed, 2),
    'peak_increment_mib' => round($peak / 1048576, 3), 'queries' => $queries,
    'result_sha256' => hash('sha256', json_encode($result, JSON_PRESERVE_ZERO_FRACTION)),
], JSON_PRETTY_PRINT).PHP_EOL;
