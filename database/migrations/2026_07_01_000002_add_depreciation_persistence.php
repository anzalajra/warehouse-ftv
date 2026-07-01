<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist depreciation PER UNIT so book value is historical, not recomputed from
 * useful-life parameters on every read (which made past book values change
 * retroactively when a parameter was edited).
 *
 *  - product_units.accumulated_depreciation : running total actually posted
 *  - depreciation_run_items                 : per-unit line for each monthly run (audit)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            if (! Schema::hasColumn('product_units', 'accumulated_depreciation')) {
                $table->decimal('accumulated_depreciation', 15, 2)->default(0)->after('residual_value');
            }
        });

        Schema::create('depreciation_run_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('depreciation_run_id')->index();
            $table->unsignedBigInteger('product_unit_id')->index();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('accumulated_after', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_run_items');

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn('accumulated_depreciation');
        });
    }
};
