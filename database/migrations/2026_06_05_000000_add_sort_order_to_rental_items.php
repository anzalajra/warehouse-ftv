<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            // Preserves the manual drag-and-drop ordering of item rows in the
            // rental editor. All RentalItem rows belonging to the same UI group
            // (product / variation) share the same sort_order value.
            $table->unsignedInteger('sort_order')->default(0)->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
