<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->timestamp('permit_acknowledged_at')->nullable()->after('tnc_accepted_at');
            $table->boolean('is_walk_in')->default(false)->after('permit_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->dropColumn(['permit_acknowledged_at', 'is_walk_in']);
        });
    }
};
