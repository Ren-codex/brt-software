<?php

namespace Tests\Feature\Permissions;

use App\Models\Module;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesAndSubmodulesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_expected_modules_and_pilot_submodules(): void
    {
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $expectedModules = [
            'sales', 'inventory', 'payroll', 'employees',
            'customers', 'accounting', 'user_management', 'dashboard',
        ];
        foreach ($expectedModules as $key) {
            $this->assertDatabaseHas('modules', ['key' => $key]);
        }

        $sales = Module::where('key', 'sales')->firstOrFail();
        $this->assertEquals(
            ['sales_orders', 'ar_invoices', 'receipts', 'sales_returns'],
            $sales->submodules->pluck('key')->all()
        );

        $inventory = Module::where('key', 'inventory')->firstOrFail();
        $this->assertEquals(
            ['purchase_orders', 'receiving', 'inventory_stocks', 'stock_returns'],
            $inventory->submodules->pluck('key')->all()
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $this->assertEquals(9, Module::count());
        $this->assertEquals(4, Module::where('key', 'sales')->firstOrFail()->submodules()->count());
    }

    public function test_seeds_payroll_submodules(): void
    {
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $payroll = Module::where('key', 'payroll')->firstOrFail();
        $this->assertEquals(
            ['payroll_processing', 'payroll_templates', 'loans', 'payroll_settings'],
            $payroll->submodules->pluck('key')->all()
        );
    }

    public function test_seeds_accounting_submodules(): void
    {
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $accounting = Module::where('key', 'accounting')->firstOrFail();
        $this->assertEquals(
            [
                'financial_reports', 'journal_entries', 'chart_of_accounts', 'cash_management',
                'petty_cash', 'expenses', 'bank_reconciliation', 'remittances',
            ],
            $accounting->submodules->pluck('key')->all()
        );
    }
}
