<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Database\Seeders\PayrollDefaultPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDefaultPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    public function test_administrator_gets_admin_module_wide_on_payroll(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(PayrollDefaultPermissionsSeeder::class);

        $role = ListRole::where('name', 'Administrator')->firstOrFail();
        $module = \App\Models\Module::where('key', 'payroll')->firstOrFail();
        $levels = RolePermission::where('role_id', $role->id)
            ->where('module_id', $module->id)
            ->whereNull('submodule_id')
            ->pluck('access_level')
            ->all();

        $this->assertEquals(['admin'], $levels);
    }

    public function test_seeder_is_idempotent(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(PayrollDefaultPermissionsSeeder::class);
        $this->seed(PayrollDefaultPermissionsSeeder::class);

        $this->assertEquals(1, RolePermission::count());
    }

    public function test_seeder_skips_roles_that_dont_exist_in_this_environment(): void
    {
        $this->seed(PayrollDefaultPermissionsSeeder::class);

        $this->assertEquals(0, RolePermission::count());
    }
}
