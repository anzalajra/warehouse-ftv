<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Persists the customer-category discount as its own breakdown layer on the
     * rental (previously it was baked into each item's daily_rate and lost). The
     * name is snapshotted so a later category rename/re-assignment doesn't alter
     * historical rentals.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('category_discount_amount', 15, 2)->default(0)->after('date_promotion_amount');
            $table->string('category_name')->nullable()->after('category_discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['category_discount_amount', 'category_name']);
        });
    }
};
