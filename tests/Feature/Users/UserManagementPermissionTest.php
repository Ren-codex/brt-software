<?php

namespace Tests\Feature\Users;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    /**
     * Every fixture holds the Administrator role (satisfies the existing
     * role:Administrator gate) and varies only the new fine-grained grant.
     */
    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'user_management')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => null, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->getJson('/users?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->getJson('/users?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/users', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('encoder');

        $response = $this->actingAs($user)->post('/users', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    /**
     * This previously asserted the opposite — that the holder was blocked even
     * with the right grant, because the routes sat inside a role:* group. That
     * was the bug: permissions assigned through the Roles screen did nothing.
     * Access is decided by the grant now.
     */
    public function test_grant_alone_admits_a_non_administrator_role(): void
    {
        $role = ListRole::create(['name' => 'Random Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'user_management')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/users?option=lists')->assertOk();
    }

    public function test_a_role_with_no_grant_for_this_module_is_still_denied(): void
    {
        $role = ListRole::create(['name' => 'Unrelated Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $this->actingAs($user)->getJson('/users?option=lists')->assertForbidden();
    }
}
