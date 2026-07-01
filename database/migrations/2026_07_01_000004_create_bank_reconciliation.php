<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank reconciliation: imported bank-statement lines matched against the book's
 * FinanceTransactions for a cash/bank account.
 *
 *  - bank_statement_lines.amount is SIGNED (+ inflow, − outflow).
 *  - finance_transactions.reconciled_at marks a book row as matched to a statement line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('finance_account_id')->index();
            $table->date('date');
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0); // signed
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('matched_transaction_id')->nullable()->index();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('import_batch')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_transactions', 'reconciled_at')) {
                $table->timestamp('reconciled_at')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropColumn('reconciled_at');
        });
    }
};
