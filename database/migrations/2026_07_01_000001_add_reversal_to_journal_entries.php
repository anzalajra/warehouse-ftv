<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds reversal + audit support to journal entries so posted entries can be
 * corrected without editing/deleting history (a core internal-control requirement):
 *  - reversal_of_id : the entry this one reverses (mirror entry)
 *  - reversed_at    : set on the original once a reversal has been posted
 *  - user_id        : who created the entry (was in the model fillable but never a column)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('description');
            }
            // Plain indexed column (no ALTER-time FK — keeps SQLite happy; the relation
            // is enforced in the JournalEntry model, not the DB).
            $table->unsignedBigInteger('reversal_of_id')->nullable()->after('reference_id')->index();
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['reversal_of_id', 'reversed_at']);
        });
    }
};
