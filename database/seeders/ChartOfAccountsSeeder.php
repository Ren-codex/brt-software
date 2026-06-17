<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ── Assets ───────────────────────────────────────────────────
            ['code' => '1010', 'name' => 'Cash on Hand',                    'type' => 'asset',     'subtype' => 'cash'],
            ['code' => '1011', 'name' => 'Cash in Bank',                    'type' => 'asset',     'subtype' => 'cash'],
            ['code' => '1020', 'name' => 'Petty Cash Fund',                 'type' => 'asset',     'subtype' => 'cash'],
            ['code' => '1100', 'name' => 'Accounts Receivable',             'type' => 'asset',     'subtype' => 'receivable'],
            ['code' => '1200', 'name' => 'Inventory',                       'type' => 'asset',     'subtype' => 'inventory'],

            // ── Liabilities ───────────────────────────────────────────────
            ['code' => '2010', 'name' => 'Accounts Payable',                'type' => 'liability', 'subtype' => 'payable'],
            ['code' => '2030', 'name' => 'Accrued Salaries & Wages',        'type' => 'liability', 'subtype' => 'accrued'],
            ['code' => '2060', 'name' => 'SSS Payable',                     'type' => 'liability', 'subtype' => 'government'],
            ['code' => '2061', 'name' => 'PhilHealth Payable',              'type' => 'liability', 'subtype' => 'government'],
            ['code' => '2062', 'name' => 'Pag-IBIG Payable',               'type' => 'liability', 'subtype' => 'government'],
            ['code' => '2070', 'name' => 'Withholding Tax Payable',         'type' => 'liability', 'subtype' => 'tax'],

            // ── Equity ────────────────────────────────────────────────────
            ['code' => '3010', 'name' => "Owner's Capital",                 'type' => 'equity',    'subtype' => 'capital'],
            ['code' => '3030', 'name' => 'Retained Earnings',               'type' => 'equity',    'subtype' => 'retained_earnings'],

            // ── Revenue ───────────────────────────────────────────────────
            ['code' => '4010', 'name' => 'Sales Revenue',                   'type' => 'revenue',   'subtype' => 'sales'],
            ['code' => '4020', 'name' => 'Sales Returns & Allowances',      'type' => 'revenue',   'subtype' => 'sales'],
            ['code' => '4030', 'name' => 'Sales Discounts',                 'type' => 'revenue',   'subtype' => 'sales'],

            // ── Cost of Sales ─────────────────────────────────────────────
            ['code' => '5010', 'name' => 'Cost of Goods Sold',              'type' => 'expense',   'subtype' => 'cost_of_sales'],
            ['code' => '5020', 'name' => 'Freight In',                      'type' => 'expense',   'subtype' => 'cost_of_sales'],
            ['code' => '5030', 'name' => 'Purchase Returns',                'type' => 'expense',   'subtype' => 'cost_of_sales'],
            ['code' => '5040', 'name' => 'Purchase Discounts',              'type' => 'expense',   'subtype' => 'cost_of_sales'],

            // ── Payroll Expenses ──────────────────────────────────────────
            ['code' => '5500', 'name' => 'Salaries & Wages',                'type' => 'expense',   'subtype' => 'payroll'],
            ['code' => '5510', 'name' => 'SSS Contribution — Employer',     'type' => 'expense',   'subtype' => 'payroll'],
            ['code' => '5511', 'name' => 'PhilHealth Contribution — Employer', 'type' => 'expense', 'subtype' => 'payroll'],
            ['code' => '5512', 'name' => 'Pag-IBIG Contribution — Employer', 'type' => 'expense',  'subtype' => 'payroll'],
        ];

        $now = now();

        foreach ($accounts as $account) {
            $slug = Str::slug($account['name']);

            // Skip if code or slug already exists
            $exists = DB::table('accounts')
                ->where('code', $account['code'])
                ->orWhere('slug', $slug)
                ->exists();

            if ($exists) {
                $this->command->line("  Skipped (already exists): {$account['code']} — {$account['name']}");
                continue;
            }

            DB::table('accounts')->insert([
                'code'       => $account['code'],
                'slug'       => $slug,
                'name'       => $account['name'],
                'type'       => $account['type'],
                'subtype'    => $account['subtype'],
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->command->line("  Created: {$account['code']} — {$account['name']}");
        }

        $this->command->info('Chart of Accounts seeder complete.');
    }
}
