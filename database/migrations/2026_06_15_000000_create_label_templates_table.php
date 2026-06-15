<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The serialize() output from the LuckPrinter editor
            // ({v, label, orientation, elements}). Stored as JSON.
            $table->longText('design');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
