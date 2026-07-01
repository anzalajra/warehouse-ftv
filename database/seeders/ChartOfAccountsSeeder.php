<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountMapping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks to allow truncation
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        AccountMapping::truncate();
        Account::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $accounts = [
            // 1. ASET (ASSETS)
            [
                'code' => '1-1000',
                'name' => 'Aset Lancar',
                'type' => 'asset',
                'subtype' => 'current_asset',
                'is_sub_account' => false,
                'children' => [
                    ['1-1100', 'Kas dan Setara Kas', 'asset', 'current_asset', true, [
                        ['1-1101', 'Kas Kecil', 'asset', 'current_asset', true],
                        ['1-1102', 'Bank BCA', 'asset', 'current_asset', true],
                        ['1-1103', 'Bank Mandiri', 'asset', 'current_asset', true],
                    ]],
                    ['1-1200', 'Piutang Usaha', 'asset', 'current_asset', true],
                    ['1-1300', 'Persediaan', 'asset', 'current_asset', true],
                    ['1-1400', 'Sewa Dibayar Dimuka', 'asset', 'current_asset', true],
                    ['1-1500', 'PPh 23 Dibayar Dimuka', 'asset', 'current_asset', true],
                ]
            ],
            [
                'code' => '1-2000',
                'name' => 'Aset Tetap',
                'type' => 'asset',
                'subtype' => 'fixed_asset',
                'is_sub_account' => false,
                'children' => [
                    ['1-2100', 'Peralatan Rental', 'asset', 'fixed_asset', true],
                    ['1-2101', 'Akum. Penyusutan Peralatan', 'asset', 'fixed_asset', true], // Usually credit balance
                    ['1-2200', 'Kendaraan', 'asset', 'fixed_asset', true],
                    ['1-2201', 'Akum. Penyusutan Kendaraan', 'asset', 'fixed_asset', true],
                ]
            ],

            // 2. LIABILITAS (LIABILITIES)
            [
                'code' => '2-1000',
                'name' => 'Liabilitas Jangka Pendek',
                'type' => 'liability',
                'subtype' => 'current_liability',
                'is_sub_account' => false,
                'children' => [
                    ['2-1100', 'Hutang Usaha', 'liability', 'current_liability', true],
                    ['2-1200', 'Uang Jaminan Pelanggan (Deposit)', 'liability', 'current_liability', true],
                    ['2-1300', 'Pendapatan Diterima Dimuka', 'liability', 'current_liability', true],
                    ['2-1400', 'Hutang Pajak', 'liability', 'current_liability', true],
                ]
            ],
            [
                'code' => '2-2000',
                'name' => 'Liabilitas Jangka Panjang',
                'type' => 'liability',
                'subtype' => 'long_term_liability',
                'is_sub_account' => false,
                'children' => [
                    ['2-2100', 'Hutang Bank', 'liability', 'long_term_liability', true],
                ]
            ],

            // 3. EKUITAS (EQUITY)
            [
                'code' => '3-1000',
                'name' => 'Ekuitas',
                'type' => 'equity',
                'subtype' => 'equity',
                'is_sub_account' => false,
                'children' => [
                    ['3-1100', 'Modal Pemilik', 'equity', 'equity', true],
                    ['3-1200', 'Prive Pemilik', 'equity', 'equity', true],
                    ['3-2000', 'Laba Ditahan', 'equity', 'equity', true],
                ]
            ],

            // 4. PENDAPATAN (REVENUE)
            [
                'code' => '4-1000',
                'name' => 'Pendapatan Usaha',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'is_sub_account' => false,
                'children' => [
                    ['4-1100', 'Pendapatan Sewa', 'revenue', 'revenue', true],
                    ['4-1200', 'Pendapatan Denda', 'revenue', 'revenue', true],
                    ['4-1300', 'Pendapatan Jasa Lainnya', 'revenue', 'revenue', true],
                ]
            ],

            // 5. BEBAN (EXPENSES)
            [
                'code' => '5-1000',
                'name' => 'Beban Pokok Pendapatan',
                'type' => 'expense',
                'subtype' => 'cogs',
                'is_sub_account' => false,
                'children' => [
                    ['5-1100', 'Beban Perawatan Aset', 'expense', 'cogs', true],
                    ['5-1200', 'Beban Penyusutan Aset Sewa', 'expense', 'cogs', true],
                    ['5-1400', 'Beban Penyusutan Peralatan', 'expense', 'cogs', true],
                    ['5-1500', 'Beban Listrik, Air, & Internet', 'expense', 'cogs', true],
                ]
            ],
            [
                'code' => '5-2000',
                'name' => 'Beban Operasional',
                'type' => 'expense',
                'subtype' => 'operating_expense',
                'is_sub_account' => false,
                'children' => [
                    ['5-2100', 'Beban Gaji', 'expense', 'operating_expense', true],
                    ['5-2200', 'Beban Sewa Kantor', 'expense', 'operating_expense', true],
                    ['5-2300', 'Beban Listrik, Air, Internet', 'expense', 'operating_expense', true],
                    ['5-2400', 'Beban Pemasaran', 'expense', 'operating_expense', true],
                    ['5-2500', 'Beban Perlengkapan', 'expense', 'operating_expense', true],
                ]
            ],

            // 6. PENDAPATAN LAIN-LAIN
            [
                'code' => '6-1000',
                'name' => 'Pendapatan Lain-lain',
                'type' => 'revenue',
                'subtype' => 'other_income',
                'is_sub_account' => false,
                'children' => [
                    ['6-1100', 'Pendapatan Bunga Bank', 'revenue', 'other_income', true],
                ]
            ],

            // 8. BEBAN LAIN-LAIN
            [
                'code' => '8-1000',
                'name' => 'Beban Lain-lain',
                'type' => 'expense',
                'subtype' => 'other_expense',
                'is_sub_account' => false,
                'children' => [
                    ['8-1100', 'Beban Administrasi Bank', 'expense', 'other_expense', true],
                    ['8-1200', 'Beban Pajak', 'expense', 'other_expense', true],
                ]
            ],
        ];

        foreach ($accounts as $accData) {
            $this->createAccountRecursive($accData);
        }

        // Create Mappings based on User Request
        $mappings = [
            // RECEIVE_RENTAL_PAYMENT -> Debit 1-1100 (Kas), Credit 2-1300 (Pendapatan Diterima Dimuka)
            ['RECEIVE_RENTAL_PAYMENT', 'debit', '1-1100'], 
            ['RECEIVE_RENTAL_PAYMENT', 'credit', '2-1300'],

            // SECURITY_DEPOSIT_IN -> Debit 1-1100 (Kas), Credit 2-1200 (Uang Jaminan Pelanggan)
            ['SECURITY_DEPOSIT_IN', 'debit', '1-1100'],
            ['SECURITY_DEPOSIT_IN', 'credit', '2-1200'],

            // SECURITY_DEPOSIT_OUT -> Debit 2-1200 (Uang Jaminan Pelanggan), Credit 1-1100 (Kas)
            ['SECURITY_DEPOSIT_OUT', 'debit', '2-1200'],
            ['SECURITY_DEPOSIT_OUT', 'credit', '1-1100'],
            
            // RENTAL_INVOICE_ISSUED -> Debit 1-1200 (Piutang), Credit 4-1100 (Pendapatan Sewa)
            // Note: This is usually when revenue is recognized if accrual.
            ['RENTAL_INVOICE_ISSUED', 'debit', '1-1200'],
            ['RENTAL_INVOICE_ISSUED', 'credit', '4-1100'],

            // SECURITY_DEPOSIT_DEDUCTION -> Debit 2-1200 (Deposit), Credit 4-1200 (Pendapatan Denda)
            ['SECURITY_DEPOSIT_DEDUCTION', 'debit', '2-1200'],
            ['SECURITY_DEPOSIT_DEDUCTION', 'credit', '4-1200'],

            // RENTAL_COMPLETION -> Debit 2-1300 (Pendapatan Diterima Dimuka), Credit 4-1100 (Pendapatan Sewa)
            ['RENTAL_COMPLETION', 'debit', '2-1300'],
            ['RENTAL_COMPLETION', 'credit', '4-1100'],

            // MONTHLY_DEPRECIATION -> Debit 5-1400 (Beban Penyusutan Peralatan), Credit 1-2101 (Akumulasi Penyusutan)
            ['MONTHLY_DEPRECIATION', 'debit', '5-1400'],
            ['MONTHLY_DEPRECIATION', 'credit', '1-2101'],
        ];

        foreach ($mappings as $map) {
            $account = Account::where('code', $map[2])->first();
            if ($account) {
                AccountMapping::create([
                    'event' => $map[0],
                    'role' => $map[1],
                    'account_id' => $account->id,
                ]);
            }
        }
    }

    private function createAccountRecursive($data, $parentId = null)
    {
        $account = Account::create([
            'code' => $data['code'],
            'name' => $data['name'] ?? $data[1], // Handle array format if needed
            'type' => $data['type'] ?? $data[2],
            'subtype' => $data['subtype'] ?? $data[3],
            'is_sub_account' => $data['is_sub_account'] ?? ($data[4] ?? false),
            'parent_id' => $parentId,
            'is_active' => true,
        ]);

        if (isset($data['children'])) {
            foreach ($data['children'] as $childData) {
                // Check if childData is array format [code, name, type, subtype, is_sub, children?]
                if (isset($childData[0])) {
                    $childDataFormatted = [
                        'code' => $childData[0],
                        'name' => $childData[1],
                        'type' => $childData[2],
                        'subtype' => $childData[3],
                        'is_sub_account' => $childData[4] ?? true,
                    ];
                    if (isset($childData[5])) {
                         $childDataFormatted['children'] = $childData[5];
                    }
                    $this->createAccountRecursive($childDataFormatted, $account->id);
                } else {
                    $this->createAccountRecursive($childData, $account->id);
                }
            }
        }
    }
}
