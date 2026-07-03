<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery routing (MVP) — fulfillment side on the rental.
 *
 * Adds how a rental is fulfilled (self-pickup vs delivered) plus the delivery
 * destination captured at order time. The physical routing (driver/escort/
 * schedule) lives on the Delivery rows (see the sibling migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('fulfillment_method')->default('pickup')->after('pricing_period');
            $table->text('delivery_address')->nullable()->after('fulfillment_method');
            $table->string('delivery_contact')->nullable()->after('delivery_address');
            $table->text('delivery_notes')->nullable()->after('delivery_contact');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_method',
                'delivery_address',
                'delivery_contact',
                'delivery_notes',
            ]);
        });
    }
};
