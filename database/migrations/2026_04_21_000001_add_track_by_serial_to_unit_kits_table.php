<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_kits', function (Blueprint $table) {
            $table->boolean('track_by_serial')->default(true)->after('linked_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('unit_kits', function (Blueprint $table) {
            $table->dropColumn('track_by_serial');
        });
    }
};
