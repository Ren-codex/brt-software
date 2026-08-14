<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function userWithGrant(?string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'customers')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => null, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Test Customer ' . uniqid(), 'address' => 'Test Address',
            'contact_number' => '09000000000', 'added_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant(null);

        $this->actingAs($user)->getJson('/customers?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('view');

        $this->actingAs($user)->getJson('/customers?option=lists')->assertOk();
    }

    public function test_details_denied_without_view_grant(): void
    {
        $customer = $this->makeCustomer();
        $user = $this->userWithGrant(null);

        $this->actingAs($user)->getJson("/customers/{$customer->id}/details")->assertForbidden();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->userWithGrant('view');

        $this->actingAs($user)->post('/customers', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->userWithGrant('encoder');

        $response = $this->actingAs($user)->post('/customers', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $customer = $this->makeCustomer();
        $user = $this->userWithGrant('encoder');

        $this->actingAs($user)->delete("/customers/{$customer->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $customer = $this->makeCustomer();
        $user = $this->userWithGrant('admin');

        $response = $this->actingAs($user)->delete("/customers/{$customer->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
