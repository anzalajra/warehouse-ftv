<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'late_fee_daily_amount')) {
                // Per-product late fee basis (per unit per day). Null = use the product's
                // daily_rate / global late fee setting. Overrides the per-day basis used by
                // all late fee modes (per_unit_per_day, percentage_per_day, tiered, ...).
                $table->decimal('late_fee_daily_amount', 12, 2)->nullable()->after('buffer_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'late_fee_daily_amount')) {
                $table->dropColumn('late_fee_daily_amount');
            }
        });
    }
};
