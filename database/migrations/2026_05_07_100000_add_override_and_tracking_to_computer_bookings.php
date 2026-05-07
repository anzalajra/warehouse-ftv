<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->boolean('is_override')->default(false)->after('is_walk_in');
            $table->foreignId('overrides_booking_id')->nullable()->after('is_override')
                ->constrained('computer_bookings')->nullOnDelete();
            $table->timestamp('actual_started_at')->nullable()->after('checked_in_at');
            $table->timestamp('actual_ended_at')->nullable()->after('actual_started_at');
            $table->unsignedInteger('actual_duration_seconds')->nullable()->after('actual_ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overrides_booking_id');
            $table->dropColumn(['is_override', 'actual_started_at', 'actual_ended_at', 'actual_duration_seconds']);
        });
    }
};
