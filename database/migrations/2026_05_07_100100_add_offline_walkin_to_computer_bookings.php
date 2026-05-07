<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->boolean('is_offline_walkin')->default(false)->after('is_override');
            $table->string('offline_walkin_name')->nullable()->after('is_offline_walkin');
            $table->uuid('offline_client_uuid')->nullable()->unique()->after('offline_walkin_name');
            // user_id can be null for offline walkins until admin assigns
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->dropColumn(['is_offline_walkin', 'offline_walkin_name', 'offline_client_uuid']);
        });
    }
};
