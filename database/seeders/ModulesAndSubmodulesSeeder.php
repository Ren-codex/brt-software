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
            ['key' => 'payroll', 'name' => 'Payroll', 'sort_order' => 3, 'submodules' => []],
            ['key' => 'employees', 'name' => 'Employee Profiling', 'sort_order' => 4, 'submodules' => []],
            ['key' => 'customers', 'name' => 'Customers & Contacts', 'sort_order' => 5, 'submodules' => []],
            ['key' => 'accounting', 'name' => 'Accounting', 'sort_order' => 6, 'submodules' => []],
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
