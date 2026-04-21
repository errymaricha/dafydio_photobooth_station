<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinanceAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'account_code' => '1100',
                'account_name' => 'Cash On Hand',
                'account_type' => 'asset',
                'normal_balance' => 'debit',
                'level_no' => 1,
            ],
            [
                'account_code' => '1110',
                'account_name' => 'Cash In Bank',
                'account_type' => 'asset',
                'normal_balance' => 'debit',
                'level_no' => 1,
            ],
            [
                'account_code' => '2100',
                'account_name' => 'Accrued Payable',
                'account_type' => 'liability',
                'normal_balance' => 'credit',
                'level_no' => 1,
            ],
            [
                'account_code' => '4100',
                'account_name' => 'Revenue Photobooth',
                'account_type' => 'revenue',
                'normal_balance' => 'credit',
                'level_no' => 1,
            ],
            [
                'account_code' => '4110',
                'account_name' => 'Revenue Additional Print',
                'account_type' => 'revenue',
                'normal_balance' => 'credit',
                'level_no' => 1,
            ],
            [
                'account_code' => '4120',
                'account_name' => 'Revenue Print Order',
                'account_type' => 'revenue',
                'normal_balance' => 'credit',
                'level_no' => 1,
            ],
            [
                'account_code' => '5100',
                'account_name' => 'Expense Operational',
                'account_type' => 'expense',
                'normal_balance' => 'debit',
                'level_no' => 1,
            ],
        ];

        foreach ($accounts as $account) {
            $existing = DB::table('finance_accounts')
                ->where('account_code', $account['account_code'])
                ->first();

            if (! $existing) {
                DB::table('finance_accounts')->insert([
                    'id' => (string) Str::uuid(),
                    'account_code' => $account['account_code'],
                    'account_name' => $account['account_name'],
                    'account_type' => $account['account_type'],
                    'normal_balance' => $account['normal_balance'],
                    'parent_id' => null,
                    'level_no' => $account['level_no'],
                    'is_active' => true,
                    'description' => 'Default finance account',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('finance_accounts')
                ->where('id', $existing->id)
                ->update([
                    'account_name' => $account['account_name'],
                    'account_type' => $account['account_type'],
                    'normal_balance' => $account['normal_balance'],
                    'level_no' => $account['level_no'],
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        }
    }
}
