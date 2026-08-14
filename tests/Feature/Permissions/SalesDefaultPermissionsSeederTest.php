<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Database\Seeders\SalesDefaultPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDefaultPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function grantLevels(string $roleName): array
    {
        $role = ListRole::where('name', $roleName)->firstOrFail();

        return RolePermission::where('role_id', $role->id)
            ->whereNull('submodule_id')
            ->pluck('access_level')
            ->sort()
            ->values()
            ->all();
    }

    public function test_administrator_gets_admin_level_module_wide(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator'));
    }

    public function test_sales_rep_gets_encoder_and_view_module_wide(): void
    {
        ListRole::create(['name' => 'Sales Rep', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(['encoder', 'view'], $this->grantLevels('Sales Rep'));
    }

    public function test_area_business_manager_gets_approver_and_view_module_wide(): void
    {
        ListRole::create(['name' => 'Area Business Manager', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(['approver', 'view'], $this->grantLevels('Area Business Manager'));
    }

    public function test_seeder_is_idempotent(): void
    {
        ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(SalesDefaultPermissionsSeeder::class);
        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator'));
    }

    public function test_seeder_skips_roles_that_dont_exist_in_this_environment(): void
    {
        // No ListRole rows created at all — seeder must not throw.
        $this->seed(SalesDefaultPermissionsSeeder::class);

        $this->assertEquals(0, RolePermission::count());
    }
}
