<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\Submodule;
use App\Models\User;
use App\Models\UserRole;
use App\Services\System\Permission\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $permissions;
    private Module $salesModule;
    private Submodule $salesOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissions = app(PermissionService::class);
        $this->salesModule = Module::create(['key' => 'sales', 'name' => 'Sales', 'sort_order' => 1]);
        $this->salesOrders = Submodule::create([
            'module_id' => $this->salesModule->id, 'key' => 'sales_orders', 'name' => 'Sales Orders', 'sort_order' => 1,
        ]);
    }

    private function userWithRole(string $roleName): array
    {
        $role = ListRole::create(['name' => $roleName, 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        // added_by_id is NOT NULL on user_roles; self-assign for test purposes.
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        return [$user, $role];
    }

    public function test_denies_when_no_grant_exists(): void
    {
        [$user] = $this->userWithRole('No Grants Role');

        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'view'));
    }

    public function test_allows_with_a_matching_submodule_specific_grant(): void
    {
        [$user, $role] = $this->userWithRole('Encoder Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'encoder',
        ]);

        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'encoder'));
        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'approver'));
    }

    public function test_module_wide_grant_satisfies_any_submodule_in_that_module(): void
    {
        [$user, $role] = $this->userWithRole('Module Wide View Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => null, 'access_level' => 'view',
        ]);

        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'view'));
        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'encoder'));
    }

    public function test_admin_level_satisfies_checks_for_the_other_three_levels(): void
    {
        [$user, $role] = $this->userWithRole('Admin Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'admin',
        ]);

        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'view'));
        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'encoder'));
        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'approver'));
        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'admin'));
    }

    public function test_holding_encoder_does_not_satisfy_an_admin_only_check(): void
    {
        [$user, $role] = $this->userWithRole('Encoder Only Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'encoder',
        ]);

        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'admin'));
    }

    public function test_super_admin_bypasses_every_check_with_no_grants(): void
    {
        [$user] = $this->userWithRole('Super Admin');

        $this->assertTrue($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'admin'));
        $this->assertTrue($this->permissions->userHasAccess($user, 'inventory', 'purchase_orders', 'approver'));
    }

    public function test_inactive_user_role_does_not_grant_access(): void
    {
        $role = ListRole::create(['name' => 'Inactive Grant Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 0, 'added_by_id' => $user->id]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->assertFalse($this->permissions->userHasAccess($user, 'sales', 'sales_orders', 'view'));
    }

    public function test_permission_map_shapes_module_wide_and_submodule_grants(): void
    {
        [$user, $role] = $this->userWithRole('Mixed Grants Role');
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'encoder',
        ]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->salesModule->id,
            'submodule_id' => $this->salesOrders->id, 'access_level' => 'view',
        ]);

        $map = $this->permissions->userPermissionMap($user);

        $this->assertEqualsCanonicalizing(['encoder', 'view'], $map['sales']['sales_orders']);
    }
}
