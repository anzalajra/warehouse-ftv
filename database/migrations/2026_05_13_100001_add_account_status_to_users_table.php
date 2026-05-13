<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 20)->default('active')->after('is_verified');
            $table->text('blocked_reason')->nullable()->after('account_status');
            $table->timestamp('blocked_at')->nullable()->after('blocked_reason');
            $table->unsignedBigInteger('blocked_by')->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_status', 'blocked_reason', 'blocked_at', 'blocked_by']);
        });
    }
};
