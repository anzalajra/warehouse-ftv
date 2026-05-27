<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the rental editor's hot path:
 *   - getBookedUnitIds: rentals(status, start_date, end_date) + rental_items(product_unit_id)
 *   - availableCountMap GROUP BY: product_units(product_id, product_variation_id, status, condition)
 *   - unit kits lookups during booking: unit_kits(unit_id), unit_kits(linked_unit_id)
 *
 * Idempotent: skips a column if its table doesn't have it (sqlite test env may lack some
 * legacy columns), and detects existing indexes by name before adding.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('rentals', 'rentals_status_dates_idx', ['status', 'start_date', 'end_date']);
        $this->addIndex('rental_items', 'rental_items_product_unit_id_idx', ['product_unit_id']);
        $this->addIndex('rental_items', 'rental_items_rental_id_idx', ['rental_id']);
        $this->addIndex('product_units', 'product_units_product_variation_idx', ['product_id', 'product_variation_id']);
        $this->addIndex('product_units', 'product_units_status_condition_idx', ['status', 'condition']);
        $this->addIndex('unit_kits', 'unit_kits_unit_id_idx', ['unit_id']);
        $this->addIndex('unit_kits', 'unit_kits_linked_unit_id_idx', ['linked_unit_id']);
        $this->addIndex('products', 'products_is_active_name_idx', ['is_active', 'name']);
    }

    public function down(): void
    {
        $this->dropIndex('rentals', 'rentals_status_dates_idx');
        $this->dropIndex('rental_items', 'rental_items_product_unit_id_idx');
        $this->dropIndex('rental_items', 'rental_items_rental_id_idx');
        $this->dropIndex('product_units', 'product_units_product_variation_idx');
        $this->dropIndex('product_units', 'product_units_status_condition_idx');
        $this->dropIndex('unit_kits', 'unit_kits_unit_id_idx');
        $this->dropIndex('unit_kits', 'unit_kits_linked_unit_id_idx');
        $this->dropIndex('products', 'products_is_active_name_idx');
    }

    protected function addIndex(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table)) return;
        foreach ($columns as $col) {
            if (!Schema::hasColumn($table, $col)) return;
        }
        if ($this->indexExists($table, $indexName)) return;
        Schema::table($table, function (Blueprint $t) use ($indexName, $columns) {
            $t->index($columns, $indexName);
        });
    }

    protected function dropIndex(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table)) return;
        if (!$this->indexExists($table, $indexName)) return;
        Schema::table($table, function (Blueprint $t) use ($indexName) {
            $t->dropIndex($indexName);
        });
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();
        try {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                $rows = DB::select(
                    "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
                    [$table, $indexName]
                );
                return count($rows) > 0;
            }
            if ($driver === 'sqlite') {
                $rows = DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$indexName]);
                return count($rows) > 0;
            }
            if ($driver === 'pgsql') {
                $rows = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $indexName]);
                return count($rows) > 0;
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }
};
