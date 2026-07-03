<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom fields on Product & Rental.
 *
 * Mirrors the existing customer custom-fields pattern (users.custom_fields JSON,
 * field definitions stored as a Setting). Field definitions live in the Setting
 * keys `product_custom_fields` / `rental_custom_fields`; the per-record values are
 * stored here as a JSON column cast to `array`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('is_visible_on_frontend');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });
    }
};
