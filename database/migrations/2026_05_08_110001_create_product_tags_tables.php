<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 32)->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('product_product_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_tag_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'product_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_product_tag');
        Schema::dropIfExists('product_tags');
    }
};
