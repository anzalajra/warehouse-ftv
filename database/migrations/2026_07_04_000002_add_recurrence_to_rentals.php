<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring / subscription rentals (draft-quotation model).
 *
 * A rental flagged `is_recurring` is cloned into a fresh QUOTATION by the
 * scheduled `rentals:generate-recurring` command when `recurrence_next_date`
 * arrives — no auto-charge; the admin reviews/confirms each generated quote.
 * The generated child points back at its source via `recurrence_parent_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('custom_fields');
            $table->string('recurrence_interval')->nullable()->after('is_recurring'); // weekly | monthly
            $table->date('recurrence_next_date')->nullable()->after('recurrence_interval');
            $table->date('recurrence_end_date')->nullable()->after('recurrence_next_date');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_end_date')
                ->constrained('rentals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn([
                'is_recurring',
                'recurrence_interval',
                'recurrence_next_date',
                'recurrence_end_date',
            ]);
        });
    }
};
