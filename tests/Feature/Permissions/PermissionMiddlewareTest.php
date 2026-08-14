<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\Submodule;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Module $inventoryModule;
    private Submodule $purchaseOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryModule = Module::create(['key' => 'inventory', 'name' => 'Inventory', 'sort_order' => 1]);
        $this->purchaseOrders = Submodule::create([
            'module_id' => $this->inventoryModule->id, 'key' => 'purchase_orders', 'name' => 'Purchase Orders', 'sort_order' => 1,
        ]);

        Route::middleware(['web', 'auth', 'permission:inventory,purchase_orders,encoder'])
            ->get('/__test/permission-submodule-check', fn () => response('ok'));

        Route::middleware(['web', 'auth', 'permission:inventory,view'])
            ->get('/__test/permission-module-check', fn () => response('ok'));
    }

    public function test_guest_is_redirected_or_denied(): void
    {
        $this->get('/__test/permission-submodule-check')->assertRedirect();
    }

    public function test_authenticated_user_without_grant_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/__test/permission-submodule-check')
            ->assertForbidden();
    }

    public function test_authenticated_user_with_matching_submodule_grant_is_allowed(): void
    {
        $role = ListRole::create(['name' => 'PO Encoder', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->inventoryModule->id,
            'submodule_id' => $this->purchaseOrders->id, 'access_level' => 'encoder',
        ]);

        $this->actingAs($user)
            ->get('/__test/permission-submodule-check')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_module_wide_two_argument_form_checks_module_level_only(): void
    {
        $role = ListRole::create(['name' => 'Inventory Viewer', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $this->inventoryModule->id,
            'submodule_id' => null, 'access_level' => 'view',
        ]);

        $this->actingAs($user)
            ->get('/__test/permission-module-check')
            ->assertOk();
    }

    public function test_super_admin_bypasses_the_middleware(): void
    {
        $role = ListRole::create(['name' => 'Super Admin', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $this->actingAs($user)
            ->get('/__test/permission-submodule-check')
            ->assertOk();
    }
}
