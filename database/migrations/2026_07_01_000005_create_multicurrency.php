<?php

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency FOUNDATION. A currency master holds exchange rates to the base
 * currency; money-bearing documents carry their currency + the rate captured at entry
 * time; the GL is always posted in BASE currency (amount × exchange_rate).
 *
 * NOT included (documented as remaining depth): per-line rental pricing in FX, and
 * period-end revaluation of open FX balances (realized/unrealized FX gain/loss).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // IDR, USD, ...
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->decimal('exchange_rate', 18, 6)->default(1); // units of BASE per 1 unit of this currency
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('amount');
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('total');
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
        });

        // Seed the base currency (IDR) if none exists.
        if (! Currency::query()->exists()) {
            Currency::create([
                'code' => 'IDR',
                'name' => 'Rupiah Indonesia',
                'symbol' => 'Rp',
                'exchange_rate' => 1,
                'is_base' => true,
                'is_active' => true,
            ]);
            Setting::set('base_currency', 'IDR');
        }
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });
        Schema::dropIfExists('currencies');
    }
};
