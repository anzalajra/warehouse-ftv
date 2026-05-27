<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            // Nullable: existing rows without these columns remain valid.
            // Ghost rows (product_unit_id IS NULL, intent-to-rent without serial assigned yet)
            // require these to know which product/variation the placeholder slot is for.
            $table->foreignId('product_id')->nullable()->after('product_unit_id')
                ->constrained('products')->nullOnDelete();
            $table->foreignId('product_variation_id')->nullable()->after('product_id')
                ->constrained('product_variations')->nullOnDelete();
        });

        // Backfill existing rows from their linked ProductUnit so that
        // group-by-product queries can rely on these columns uniformly.
        // Chunked to keep memory bounded on large tables.
        DB::table('rental_items')
            ->whereNotNull('product_unit_id')
            ->select('id', 'product_unit_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $unitIds = $rows->pluck('product_unit_id')->all();
                $units = DB::table('product_units')
                    ->whereIn('id', $unitIds)
                    ->get(['id', 'product_id', 'product_variation_id'])
                    ->keyBy('id');

                foreach ($rows as $r) {
                    $u = $units[$r->product_unit_id] ?? null;
                    if (!$u) continue;
                    DB::table('rental_items')
                        ->where('id', $r->id)
                        ->update([
                            'product_id' => $u->product_id,
                            'product_variation_id' => $u->product_variation_id,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variation_id');
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
