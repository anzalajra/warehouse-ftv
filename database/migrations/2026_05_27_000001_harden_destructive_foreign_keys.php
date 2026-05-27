<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harden destructive ON DELETE CASCADE foreign keys that previously could
 * wipe finance / rental / brand history if a parent record was deleted.
 *
 * Strategy:
 *   - Finance integrity (P0): RESTRICT — block deletion to protect ledger.
 *   - Brand → Products (P1): SET NULL — products survive, become "Unbranded".
 *   - Operational history (P2): RESTRICT — block unit/user deletion when
 *     historical rentals/maintenance exist; admin must archive instead.
 *   - computer_bookings.user_id: SET NULL — anonymize on user deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- P0: Finance integrity (RESTRICT) ---

        Schema::table('invoices', function (Blueprint $table) {
            // Column was renamed customer_id → user_id in migration 2026_02_11_114500.
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('journal_entry_items', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropForeign(['finance_account_id']);
            $table->foreign('finance_account_id')->references('id')->on('finance_accounts')->restrictOnDelete();
        });

        Schema::table('category_mappings', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
        });

        // --- P1: Brand → Products (SET NULL, mirror of Category fix) ---

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable()->change();
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
        });

        // --- P2: Operational history (RESTRICT) ---

        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->foreign('product_unit_id')->references('id')->on('product_units')->restrictOnDelete();
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->foreign('product_unit_id')->references('id')->on('product_units')->restrictOnDelete();
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        // computer_bookings.user_id → SET NULL (anonymize on user deletion)
        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Revert to original CASCADE behavior (destructive — not recommended).

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('journal_entry_items', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropForeign(['finance_account_id']);
            $table->foreign('finance_account_id')->references('id')->on('finance_accounts')->cascadeOnDelete();
        });

        Schema::table('category_mappings', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable(false)->change();
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
        });

        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->foreign('product_unit_id')->references('id')->on('product_units')->cascadeOnDelete();
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropForeign(['product_unit_id']);
            $table->foreign('product_unit_id')->references('id')->on('product_units')->cascadeOnDelete();
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('computer_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
