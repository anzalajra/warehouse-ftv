<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery routing (MVP) — routing side on each Delivery (surat jalan) row.
 *
 * Assigns a driver + escort/pengawal alat, a scheduled datetime and destination
 * address so deliveries can be grouped per driver per day on the Jadwal
 * Pengiriman page. No map / route optimization — this is operational only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('checked_by')->constrained('users')->nullOnDelete();
            $table->foreignId('escort_id')->nullable()->after('driver_id')->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable()->after('date');
            $table->text('address')->nullable()->after('scheduled_at');
            $table->integer('sort_order')->default(0)->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropConstrainedForeignId('escort_id');
            $table->dropColumn(['scheduled_at', 'address', 'sort_order']);
        });
    }
};
