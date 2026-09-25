<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dateTime('effective_due_at')->nullable();
            $table->dateTime('overtime_started_at')->nullable();
            $table->boolean('late_fee_waived')->default(false);
            $table->decimal('extension_charge', 15, 2)->default(0);
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('late_fee_adjustment', 15, 2)->default(0);
        });

        Schema::create('rental_item_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_item_id')->constrained()->cascadeOnDelete();
            $table->dateTime('old_due_at');
            $table->dateTime('new_due_at');
            $table->dateTime('started_at');
            $table->decimal('charge', 15, 2)->default(0);
            $table->boolean('late_fee_waived')->default(false);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_overlap_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conflicting_rental_item_id')->constrained('rental_items')->cascadeOnDelete();
            $table->dateTime('overlap_start');
            $table->dateTime('overlap_end')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamps();
        });

        // Existing partial returns have no per-item boundary yet. The first
        // completed IN delivery is the best available historical split point.
        DB::table('rentals')->whereIn('status', ['partial_return', 'late_return'])
            ->orderBy('id')->chunkById(200, function ($rentals) {
                foreach ($rentals as $rental) {
                    $firstReturn = DB::table('deliveries')
                        ->where('rental_id', $rental->id)->where('type', 'in')
                        ->where('status', 'completed')->orderBy('id')->first();
                    if (! $firstReturn) {
                        continue;
                    }
                    $pendingItemIds = DB::table('delivery_items')
                        ->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
                        ->where('deliveries.rental_id', $rental->id)
                        ->where('deliveries.type', 'in')
                        ->whereIn('deliveries.status', ['draft', 'pending'])
                        ->whereNull('delivery_items.rental_item_kit_id')
                        ->pluck('delivery_items.rental_item_id');
                    if ($pendingItemIds->isNotEmpty()) {
                        DB::table('rental_items')->whereIn('id', $pendingItemIds)
                            ->update(['overtime_started_at' => $firstReturn->updated_at]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_overlap_overrides');
        Schema::dropIfExists('rental_item_extensions');
        Schema::table('rentals', fn (Blueprint $table) => $table->dropColumn('late_fee_adjustment'));
        Schema::table('rental_items', fn (Blueprint $table) => $table->dropColumn(['effective_due_at', 'overtime_started_at', 'late_fee_waived', 'extension_charge']));
    }
};
