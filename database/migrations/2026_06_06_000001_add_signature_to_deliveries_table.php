<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->after('checked_by');
            $table->longText('recipient_signature')->nullable()->after('recipient_name');
            $table->timestamp('signed_at')->nullable()->after('recipient_signature');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['recipient_name', 'recipient_signature', 'signed_at']);
        });
    }
};
