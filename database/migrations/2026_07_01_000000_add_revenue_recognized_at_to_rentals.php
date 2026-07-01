<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks when a rental's rental revenue has been recognized to the GL.
 *
 * Used by RentalAccountingService::postRevenueRecognition() to guarantee revenue
 * is recognized EXACTLY ONCE (the old flow double-posted: once at invoice issue and
 * again at completion). Cleared by Rental::reopenFromCompleted() so a re-completed
 * rental recognizes again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->timestamp('revenue_recognized_at')->nullable()->after('security_deposit_status');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('revenue_recognized_at');
        });
    }
};
