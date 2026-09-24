<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shooting_itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('production_name');
            $table->timestamps();
        });

        Schema::create('shooting_itinerary_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_itinerary_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('location_name');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->timestamps();
        });

        Schema::create('shooting_itinerary_crew', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_itinerary_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('name');
            $table->string('nim', 50);
            $table->string('role', 100);
            $table->timestamps();
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->foreignId('shooting_itinerary_id')
                ->nullable()
                ->constrained('shooting_itineraries')
                ->nullOnDelete();
            $table->string('usage_type')->nullable();
            $table->string('outside_purpose')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shooting_itinerary_id');
            $table->dropColumn(['usage_type', 'outside_purpose']);
        });
        Schema::dropIfExists('shooting_itinerary_crew');
        Schema::dropIfExists('shooting_itinerary_locations');
        Schema::dropIfExists('shooting_itineraries');
    }
};
