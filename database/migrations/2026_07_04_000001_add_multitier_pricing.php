<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tier pricing (per-rental period).
 *
 * Adds optional hourly/weekly/monthly rates alongside the existing daily_rate on
 * products + variations. The chosen billing period is stored per-rental
 * (rentals.pricing_period) and audited per line (rental_items.rate_type + carts
 * for the storefront). The meaning of the existing daily_rate/days columns is
 * generalized: daily_rate = "rate for the selected period", days = "number of
 * periods" — so the subtotal formula (daily_rate * days) never changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('hourly_rate', 15, 2)->nullable()->after('daily_rate');
            $table->decimal('weekly_rate', 15, 2)->nullable()->after('hourly_rate');
            $table->decimal('monthly_rate', 15, 2)->nullable()->after('weekly_rate');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->decimal('hourly_rate', 15, 2)->nullable()->after('daily_rate');
            $table->decimal('weekly_rate', 15, 2)->nullable()->after('hourly_rate');
            $table->decimal('monthly_rate', 15, 2)->nullable()->after('weekly_rate');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->string('pricing_period')->default('day')->after('end_date');
        });

        Schema::table('rental_items', function (Blueprint $table) {
            $table->string('rate_type')->default('day')->after('days');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->string('pricing_period')->default('day')->after('days');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate', 'weekly_rate', 'monthly_rate']);
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate', 'weekly_rate', 'monthly_rate']);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('pricing_period');
        });

        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropColumn('rate_type');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('pricing_period');
        });
    }
};
