<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Records WHEN each delivery item was actually checked in/out. For the IN
     * (return) direction this freezes the moment an item physically came back,
     * which lets the late-fee calculation charge each item only for its own
     * overdue window — crucial for partial returns where some items come back on
     * time and others much later. Set automatically by the DeliveryItem saving
     * hook whenever is_checked flips true; cleared when it flips back to false.
     */
    public function up(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->timestamp('checked_at')->nullable()->after('is_checked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn('checked_at');
        });
    }
};
