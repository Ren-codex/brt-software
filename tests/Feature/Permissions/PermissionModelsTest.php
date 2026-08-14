<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\Submodule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_submodule_and_role_permission_relationships_resolve(): void
    {
        $module = Module::create(['key' => 'sales', 'name' => 'Sales', 'sort_order' => 1]);
        $submodule = Submodule::create([
            'module_id' => $module->id,
            'key' => 'sales_orders',
            'name' => 'Sales Orders',
            'sort_order' => 1,
        ]);
        $role = ListRole::create([
            'name' => 'Test Encoder Role',
            'type' => 'role',
            'definition' => 'For testing',
            'is_active' => true,
        ]);

        $grant = RolePermission::create([
            'role_id' => $role->id,
            'module_id' => $module->id,
            'submodule_id' => $submodule->id,
            'access_level' => 'encoder',
        ]);

        $this->assertTrue($module->submodules->contains($submodule));
        $this->assertTrue($module->rolePermissions->contains($grant));
        $this->assertTrue($submodule->rolePermissions->contains($grant));
        $this->assertTrue($role->permissions->contains($grant));
        $this->assertEquals('sales', $grant->module->key);
        $this->assertEquals('sales_orders', $grant->submodule->key);
        $this->assertEquals($role->id, $grant->role->id);
    }

    public function test_role_permission_submodule_id_can_be_null_for_module_wide_grant(): void
    {
        $module = Module::create(['key' => 'inventory', 'name' => 'Inventory', 'sort_order' => 2]);
        $role = ListRole::create([
            'name' => 'Test Admin Role',
            'type' => 'role',
            'definition' => 'For testing',
            'is_active' => true,
        ]);

        $grant = RolePermission::create([
            'role_id' => $role->id,
            'module_id' => $module->id,
            'submodule_id' => null,
            'access_level' => 'admin',
        ]);

        $this->assertNull($grant->submodule_id);
        $this->assertNull($grant->submodule);
    }
}
