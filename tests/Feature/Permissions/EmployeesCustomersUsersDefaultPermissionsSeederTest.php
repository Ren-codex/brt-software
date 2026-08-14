<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use Database\Seeders\EmployeesCustomersUsersDefaultPermissionsSeeder;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeesCustomersUsersDefaultPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function grantLevels(string $roleName, string $moduleKey): array
    {
        $role = ListRole::where('name', $roleName)->firstOrFail();
        $module = \App\Models\Module::where('key', $moduleKey)->firstOrFail();

        return RolePermission::where('role_id', $role->id)
            ->where('module_id', $module->id)
            ->whereNull('submodule_id')
            ->pluck('access_level')
            ->sort()
            ->values()
            ->all();
    }

    public function test_administrator_gets_admin_on_all_three_modules(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator', 'employees'));
        $this->assertEquals(['admin'], $this->grantLevels('Administrator', 'customers'));
        $this->assertEquals(['admin'], $this->grantLevels('Administrator', 'user_management'));
    }

    public function test_hr_manager_gets_admin_on_employees_only(): void
    {
        ListRole::create(['name' => 'HR Manager', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('HR Manager', 'employees'));
        $this->assertEquals(0, RolePermission::whereHas('role', fn ($q) => $q->where('name', 'HR Manager'))
            ->whereHas('module', fn ($q) => $q->where('key', 'customers'))->count());
    }

    public function test_mini_admin_gets_admin_on_customers_only(): void
    {
        ListRole::create(['name' => 'Mini Admin', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Mini Admin', 'customers'));
        $this->assertEquals(0, RolePermission::whereHas('role', fn ($q) => $q->where('name', 'Mini Admin'))
            ->whereHas('module', fn ($q) => $q->where('key', 'employees'))->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);
        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator', 'employees'));
    }

    public function test_seeder_skips_roles_that_dont_exist_in_this_environment(): void
    {
        $this->seed(EmployeesCustomersUsersDefaultPermissionsSeeder::class);

        $this->assertEquals(0, RolePermission::count());
    }
}
