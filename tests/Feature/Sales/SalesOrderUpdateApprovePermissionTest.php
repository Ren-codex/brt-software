<?php

namespace Tests\Feature\Sales;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderUpdateApprovePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['for-payment', 'sales-return-approval'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucwords(str_replace('-', ' ', $slug)), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function userWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeOrder(string $statusSlug): SalesOrder
    {
        return SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(), 'payment_mode' => 'Cash',
            'order_date' => now()->toDateString(), 'added_by_id' => User::factory()->create()->id,
            'status_id' => ListStatus::where('slug', $statusSlug)->first()->id,
        ]);
    }

    public function test_create_denied_without_encoder_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'view');

        $this->actingAs($user)->post('/sales-orders', [])->assertForbidden();
    }

    public function test_create_allowed_with_encoder_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'encoder');

        $response = $this->actingAs($user)->post('/sales-orders', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_edit_denied_without_encoder_grant(): void
    {
        $order = $this->makeOrder('for-payment');
        $user = $this->userWithGrant('sales_orders', 'approver'); // approver, not encoder

        $this->actingAs($user)->put("/sales-orders/{$order->id}", [])->assertForbidden();
    }

    public function test_adjustment_denied_without_encoder_grant(): void
    {
        $order = $this->makeOrder('for-payment');
        $user = $this->userWithGrant('sales_orders', 'approver');

        $this->actingAs($user)
            ->put("/sales-orders/{$order->id}", ['action' => 'adjustment'])
            ->assertForbidden();
    }

    public function test_approving_a_regular_order_requires_sales_orders_approver(): void
    {
        $order = $this->makeOrder('for-payment');
        $returnsOnlyUser = $this->userWithGrant('sales_returns', 'approver');

        $this->actingAs($returnsOnlyUser)
            ->put("/sales-orders/{$order->id}", ['action' => 'approve'])
            ->assertForbidden();

        $ordersApprover = $this->userWithGrant('sales_orders', 'approver');

        $response = $this->actingAs($ordersApprover)
            ->put("/sales-orders/{$order->id}", ['action' => 'approve']);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_approving_a_return_requires_sales_returns_approver(): void
    {
        $order = $this->makeOrder('sales-return-approval');
        $ordersOnlyUser = $this->userWithGrant('sales_orders', 'approver');

        $this->actingAs($ordersOnlyUser)
            ->put("/sales-orders/{$order->id}", ['action' => 'approve'])
            ->assertForbidden();

        $returnsApprover = $this->userWithGrant('sales_returns', 'approver');

        $response = $this->actingAs($returnsApprover)
            ->put("/sales-orders/{$order->id}", ['action' => 'approve']);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_external_sales_orders_route_is_not_affected(): void
    {
        // sales-orders-external is out of this pilot's scope — a user with
        // zero Sales grants must still be able to reach it unchanged.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/sales-orders-external', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
