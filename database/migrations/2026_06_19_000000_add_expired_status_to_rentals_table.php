<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the 'expired' status to the rentals status ENUM.
     *
     * An unconfirmed quotation whose pickup date passes now becomes 'expired'
     * (a dead-end like 'cancelled') instead of being auto-promoted to 'late_pickup'.
     * SQLite stores the column as free text, so it only needs the MySQL ENUM widened.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE rentals MODIFY COLUMN status ENUM('quotation','confirmed','active','completed','cancelled','late_pickup','late_return','partial_return','expired') NOT NULL DEFAULT 'quotation'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Revert any expired rows back to quotation so the narrower ENUM accepts them.
            DB::table('rentals')->where('status', 'expired')->update(['status' => 'quotation']);

            DB::statement("ALTER TABLE rentals MODIFY COLUMN status ENUM('quotation','confirmed','active','completed','cancelled','late_pickup','late_return','partial_return') NOT NULL DEFAULT 'quotation'");
        }
    }
};
