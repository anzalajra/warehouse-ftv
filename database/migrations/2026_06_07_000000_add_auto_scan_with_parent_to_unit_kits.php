<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('unit_kits', 'auto_scan_with_parent')) {
            return;
        }

        Schema::table('unit_kits', function (Blueprint $table) {
            $table->boolean('auto_scan_with_parent')
                ->default(false)
                ->after('track_by_serial');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('unit_kits', 'auto_scan_with_parent')) {
            return;
        }

        Schema::table('unit_kits', function (Blueprint $table) {
            $table->dropColumn('auto_scan_with_parent');
        });
    }
};
