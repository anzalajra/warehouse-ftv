<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('notes')->index();
            $table->timestamp('last_heartbeat_at')->nullable()->after('last_seen_at');
            $table->json('last_heartbeat_data')->nullable()->after('last_heartbeat_at');
            $table->string('kiosk_token', 64)->nullable()->unique()->after('last_heartbeat_data');
            $table->timestamp('kiosk_paired_at')->nullable()->after('kiosk_token');
        });
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn([
                'last_seen_at',
                'last_heartbeat_at',
                'last_heartbeat_data',
                'kiosk_token',
                'kiosk_paired_at',
            ]);
        });
    }
};
