<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rentals', 'activity_log')) {
            return;
        }

        Schema::table('rentals', function (Blueprint $table) {
            $table->json('activity_log')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rentals', 'activity_log')) {
            return;
        }

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('activity_log');
        });
    }
};
