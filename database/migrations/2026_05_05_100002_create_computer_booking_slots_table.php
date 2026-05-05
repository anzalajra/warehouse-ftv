<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computer_booking_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sun .. 6=Sat
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['day_of_week', 'start_time', 'end_time'], 'computer_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computer_booking_slots');
    }
};
