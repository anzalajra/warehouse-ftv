<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('date');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->string('type')->default('corrective')->after('status'); // corrective, preventive, inspection
            $table->foreignId('unit_kit_id')->nullable()->after('product_unit_id')
                ->constrained('unit_kits')->nullOnDelete();
            // Source rental that the unit/kit was returned/picked up from when flagged.
            $table->foreignId('rental_id')->nullable()->after('unit_kit_id')
                ->constrained('rentals')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rental_id');
            $table->dropConstrainedForeignId('unit_kit_id');
            $table->dropColumn(['started_at', 'completed_at', 'type']);
        });
    }
};
