<?php

namespace Tests\Feature\Libraries;

use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibrariesPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function administratorWithGrant(?string $submoduleKey, ?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'libraries')->firstOrFail();
            $submodule = $module->submodules()->where('key', $submoduleKey)->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    public function test_brands_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null, null);

        $this->actingAs($user)->get('/libraries/brands')->assertForbidden();
    }

    public function test_brands_index_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('brands', 'view');

        $this->actingAs($user)->get('/libraries/brands')->assertOk();
    }

    public function test_brands_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('brands', 'view');

        $this->actingAs($user)->post('/libraries/brands', ['name' => 'Test Brand'])->assertForbidden();
    }

    public function test_brands_store_allowed_with_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('brands', 'encoder');

        $response = $this->actingAs($user)->post('/libraries/brands', ['name' => 'Test Brand']);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_brands_destroy_denied_without_admin_grant(): void
    {
        $brand = ListBrand::create(['name' => 'Deletable Brand']);
        $user = $this->administratorWithGrant('brands', 'encoder');

        $this->actingAs($user)->delete("/libraries/brands/{$brand->id}")->assertForbidden();
    }

    public function test_brands_destroy_allowed_with_admin_grant(): void
    {
        $brand = ListBrand::create(['name' => 'Deletable Brand']);
        $user = $this->administratorWithGrant('brands', 'admin');

        $response = $this->actingAs($user)->delete("/libraries/brands/{$brand->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_roles_index_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null, null);

        $this->actingAs($user)->get('/libraries/roles')->assertForbidden();
    }

    public function test_role_permissions_update_denied_without_admin_grant(): void
    {
        $target = ListRole::create(['name' => 'Target Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = $this->administratorWithGrant('roles', 'view');

        $this->actingAs($user)->post("/libraries/roles/{$target->id}/permissions", ['grants' => []])->assertForbidden();
    }
}
