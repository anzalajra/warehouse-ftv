<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a per-delivery-row "not taken" flag. Set only on OUT delivery kit rows
     * during Pickup, it records that the customer declined that accessory. The IN
     * delivery (Return) then skips creating a row for it, so the kit never appears
     * in the return checklist. The parent unit is still picked up normally.
     */
    public function up(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->boolean('not_taken')->default(false)->after('is_checked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn('not_taken');
        });
    }
};
