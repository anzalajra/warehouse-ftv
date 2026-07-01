<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PPh 23 withholding: when a (usually corporate) customer withholds PPh 23 from a
 * rental/service invoice, we receive the net cash and the withheld amount becomes our
 * prepaid income tax (a tax credit / asset: 1-1500 PPh 23 Dibayar Dimuka).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('pph23_withheld')->default(false)->after('pph_amount');
            $table->decimal('pph23_rate', 8, 2)->default(0)->after('pph23_withheld');
            $table->decimal('pph23_amount', 15, 2)->default(0)->after('pph23_rate');
            $table->string('pph23_bukti_potong_number')->nullable()->after('pph23_amount');
        });

        // Ensure the prepaid-tax GL account exists (idempotent for already-installed DBs).
        if (! Account::where('code', '1-1500')->exists()) {
            $parentId = Account::where('code', '1-1000')->value('id');
            Account::create([
                'code' => '1-1500',
                'name' => 'PPh 23 Dibayar Dimuka',
                'type' => 'asset',
                'subtype' => 'current_asset',
                'is_sub_account' => true,
                'parent_id' => $parentId,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['pph23_withheld', 'pph23_rate', 'pph23_amount', 'pph23_bukti_potong_number']);
        });
    }
};
