<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computer_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('computer_id')->constrained()->cascadeOnDelete();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('purpose');
            $table->enum('status', ['confirmed', 'active', 'completed', 'cancelled', 'no_show'])->default('confirmed');
            $table->text('admin_notes')->nullable();
            $table->timestamp('tnc_accepted_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['computer_id', 'booking_date']);
            $table->index(['user_id', 'booking_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computer_bookings');
    }
};
