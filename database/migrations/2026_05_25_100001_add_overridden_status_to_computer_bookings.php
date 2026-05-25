<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `computer_bookings` MODIFY `status` ENUM('confirmed', 'active', 'completed', 'cancelled', 'no_show', 'overridden') NOT NULL DEFAULT 'confirmed'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE `computer_bookings` SET `status` = 'cancelled' WHERE `status` = 'overridden'");
        DB::statement("ALTER TABLE `computer_bookings` MODIFY `status` ENUM('confirmed', 'active', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'confirmed'");
    }
};
