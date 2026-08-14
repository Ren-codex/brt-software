<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Submodule;
use Illuminate\Database\Seeder;

class ModulesAndSubmodulesSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['key' => 'sales', 'name' => 'Sales', 'sort_order' => 1, 'submodules' => [
                ['key' => 'sales_orders', 'name' => 'Sales Orders', 'sort_order' => 1],
                ['key' => 'ar_invoices', 'name' => 'AR Invoices', 'sort_order' => 2],
                ['key' => 'receipts', 'name' => 'Receipts', 'sort_order' => 3],
                ['key' => 'sales_returns', 'name' => 'Sales Returns', 'sort_order' => 4],
            ]],
            ['key' => 'inventory', 'name' => 'Inventory', 'sort_order' => 2, 'submodules' => [
                ['key' => 'purchase_orders', 'name' => 'Purchase Orders', 'sort_order' => 1],
                ['key' => 'receiving', 'name' => 'Receiving', 'sort_order' => 2],
                ['key' => 'inventory_stocks', 'name' => 'Inventory Stocks', 'sort_order' => 3],
                ['key' => 'stock_returns', 'name' => 'Stock Returns', 'sort_order' => 4],
            ]],
            ['key' => 'payroll', 'name' => 'Payroll', 'sort_order' => 3, 'submodules' => [
                ['key' => 'payroll_processing', 'name' => 'Payroll Processing', 'sort_order' => 1],
                ['key' => 'payroll_templates', 'name' => 'Payroll Templates', 'sort_order' => 2],
                ['key' => 'loans', 'name' => 'Loans', 'sort_order' => 3],
                ['key' => 'payroll_settings', 'name' => 'Payroll Settings', 'sort_order' => 4],
            ]],
            ['key' => 'employees', 'name' => 'Employee Profiling', 'sort_order' => 4, 'submodules' => []],
            ['key' => 'customers', 'name' => 'Customers & Contacts', 'sort_order' => 5, 'submodules' => []],
            ['key' => 'accounting', 'name' => 'Accounting', 'sort_order' => 6, 'submodules' => [
                ['key' => 'financial_reports', 'name' => 'Financial Reports', 'sort_order' => 1],
                ['key' => 'journal_entries', 'name' => 'Journal Entries', 'sort_order' => 2],
                ['key' => 'chart_of_accounts', 'name' => 'Chart of Accounts', 'sort_order' => 3],
                ['key' => 'cash_management', 'name' => 'Cash Management', 'sort_order' => 4],
                ['key' => 'petty_cash', 'name' => 'Petty Cash', 'sort_order' => 5],
                ['key' => 'expenses', 'name' => 'Expenses', 'sort_order' => 6],
                ['key' => 'bank_reconciliation', 'name' => 'Bank Reconciliation', 'sort_order' => 7],
                ['key' => 'remittances', 'name' => 'Remittances', 'sort_order' => 8],
            ]],
            ['key' => 'user_management', 'name' => 'User Management', 'sort_order' => 7, 'submodules' => []],
            ['key' => 'dashboard', 'name' => 'Dashboard', 'sort_order' => 8, 'submodules' => []],
        ];

        foreach ($catalog as $entry) {
            $module = Module::firstOrCreate(
                ['key' => $entry['key']],
                ['name' => $entry['name'], 'sort_order' => $entry['sort_order']]
            );

            foreach ($entry['submodules'] as $sub) {
                Submodule::firstOrCreate(
                    ['module_id' => $module->id, 'key' => $sub['key']],
                    ['name' => $sub['name'], 'sort_order' => $sub['sort_order']]
                );
            }
        }
    }
}
