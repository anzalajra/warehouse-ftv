<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('computer_id')->constrained()->cascadeOnDelete();
            $table->string('command', 32);
            $table->string('status', 16)->default('pending');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['computer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_commands');
    }
};
