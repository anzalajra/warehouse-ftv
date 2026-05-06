<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('id')->constrained('computer_rooms')->nullOnDelete();
            $table->string('checkin_slug')->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn('checkin_slug');
        });
    }
};
