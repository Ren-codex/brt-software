<?php

namespace Tests\Feature\Permissions;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\Submodule;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaPermissionsShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_props_include_the_users_permission_map(): void
    {
        $module = Module::create(['key' => 'sales', 'name' => 'Sales', 'sort_order' => 1]);
        $submodule = Submodule::create([
            'module_id' => $module->id, 'key' => 'sales_orders', 'name' => 'Sales Orders', 'sort_order' => 1,
        ]);
        $role = ListRole::create(['name' => 'Share Test Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submodule->id, 'access_level' => 'view',
        ]);

        $this->actingAs($user);

        $shared = app(HandleInertiaRequests::class)->share(app('request'));

        $this->assertEquals(['view'], $shared['permissions']['sales']['sales_orders']);
    }

    public function test_guest_gets_an_empty_permission_map(): void
    {
        $shared = app(HandleInertiaRequests::class)->share(app('request'));

        $this->assertEquals([], $shared['permissions']);
    }
}
