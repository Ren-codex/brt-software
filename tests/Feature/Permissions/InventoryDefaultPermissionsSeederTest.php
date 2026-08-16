<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use Database\Seeders\InventoryDefaultPermissionsSeeder;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDefaultPermissionsSeederTest extends TestCase
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

        $this->seed(InventoryDefaultPermissionsSeeder::class);

        $this->assertEquals(['admin'], $this->grantLevels('Administrator'));
    }

    public function test_warehouse_manager_gets_encoder_approver_view_and_void_module_wide(): void
    {
        ListRole::create(['name' => 'Warehouse Manager', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(InventoryDefaultPermissionsSeeder::class);

        $this->assertEquals(['approver', 'encoder', 'view', 'void'], $this->grantLevels('Warehouse Manager'));
    }

    public function test_seeder_is_idempotent(): void
    {
        ListRole::create(['name' => 'Warehouse Manager', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $this->seed(InventoryDefaultPermissionsSeeder::class);
        $this->seed(InventoryDefaultPermissionsSeeder::class);

        $this->assertEquals(['approver', 'encoder', 'view', 'void'], $this->grantLevels('Warehouse Manager'));
    }

    public function test_seeder_skips_roles_that_dont_exist_in_this_environment(): void
    {
        $this->seed(InventoryDefaultPermissionsSeeder::class);

        $this->assertEquals(0, RolePermission::count());
    }
}
