<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\RolePermission;
use App\Models\User;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $this->withoutMiddleware();
        $this->actingAs(User::factory()->create());
    }

    public function test_show_returns_full_catalog_with_empty_levels_when_role_has_no_grants(): void
    {
        $role = ListRole::create(['name' => 'Fresh Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);

        $response = $this->getJson("/libraries/roles/{$role->id}/permissions");

        $response->assertOk();
        $salesModule = collect($response->json('modules'))->firstWhere('key', 'sales');
        $this->assertNotNull($salesModule);
        $this->assertEquals([], $salesModule['levels']);
        $salesOrders = collect($salesModule['submodules'])->firstWhere('key', 'sales_orders');
        $this->assertEquals([], $salesOrders['levels']);
    }

    public function test_show_reflects_existing_grants(): void
    {
        $role = ListRole::create(['name' => 'Granted Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $module = \App\Models\Module::where('key', 'inventory')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'view',
        ]);

        $response = $this->getJson("/libraries/roles/{$role->id}/permissions");

        $inventory = collect($response->json('modules'))->firstWhere('key', 'inventory');
        $this->assertEquals(['view'], $inventory['levels']);
    }

    public function test_update_replaces_the_roles_grants(): void
    {
        $role = ListRole::create(['name' => 'Updatable Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $salesModule = \App\Models\Module::where('key', 'sales')->firstOrFail();
        $salesOrders = $salesModule->submodules()->where('key', 'sales_orders')->firstOrFail();

        // Pre-existing grant that should be removed by the update.
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $salesModule->id,
            'submodule_id' => null, 'access_level' => 'view',
        ]);

        $response = $this->postJson("/libraries/roles/{$role->id}/permissions", [
            'grants' => [
                ['module_id' => $salesModule->id, 'submodule_id' => $salesOrders->id, 'access_level' => 'encoder'],
                ['module_id' => $salesModule->id, 'submodule_id' => $salesOrders->id, 'access_level' => 'approver'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('role_permissions', 2);
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $role->id, 'submodule_id' => null]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id, 'module_id' => $salesModule->id,
            'submodule_id' => $salesOrders->id, 'access_level' => 'encoder',
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id, 'module_id' => $salesModule->id,
            'submodule_id' => $salesOrders->id, 'access_level' => 'approver',
        ]);
    }

    public function test_update_rejects_an_invalid_access_level(): void
    {
        $role = ListRole::create(['name' => 'Invalid Level Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $salesModule = \App\Models\Module::where('key', 'sales')->firstOrFail();

        $response = $this->postJson("/libraries/roles/{$role->id}/permissions", [
            'grants' => [
                ['module_id' => $salesModule->id, 'submodule_id' => null, 'access_level' => 'super-hacker'],
            ],
        ]);

        $response->assertStatus(422);
    }
}
