<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mufise_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('connection_id');
            $table->string('server_url');
            $table->string('mufise_user_id')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('pairing')->nullable();
            $table->timestamp('next_poll_at')->nullable();
            $table->timestamps();
            $table->unique(['warehouse_user_id', 'connection_id', 'server_url'], 'mufise_account_identity');
        });
        Schema::create('mufise_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('mufise_account_id')->constrained()->cascadeOnDelete();
            $table->string('project_id');
            $table->string('path');
            $table->string('method', 8);
            $table->longText('payload');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('rentals', function (Blueprint $table) {
            $table->foreignId('mufise_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('mufise_required')->default(false);
            $table->string('mufise_project_id')->nullable();
            $table->string('mufise_project_title')->nullable();
            $table->longText('mufise_submission_snapshot')->nullable();
            $table->longText('mufise_pickup_snapshot')->nullable();
            $table->longText('mufise_call_sheet_html')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mufise_account_id');
            $table->dropColumn(['mufise_required', 'mufise_project_id', 'mufise_project_title', 'mufise_submission_snapshot', 'mufise_pickup_snapshot', 'mufise_call_sheet_html']);
        });
        Schema::dropIfExists('mufise_operations');
        Schema::dropIfExists('mufise_accounts');
    }
};
