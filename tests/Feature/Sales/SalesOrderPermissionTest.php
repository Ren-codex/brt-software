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

class SalesOrderPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'for-payment'], [
            'name' => 'For Payment', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
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

    private function makeOrder(): SalesOrder
    {
        return SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(), 'payment_mode' => 'Cash',
            'order_date' => now()->toDateString(), 'added_by_id' => User::factory()->create()->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant('ar_invoices', 'view');

        $this->actingAs($user)->getJson('/sales-orders?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'view');

        $this->actingAs($user)->getJson('/sales-orders?option=lists')->assertOk();
    }

    public function test_show_page_denied_without_view_grant(): void
    {
        $order = $this->makeOrder();
        $user = $this->userWithGrant('ar_invoices', 'view');

        $this->actingAs($user)->get("/sales-orders/{$order->id}")->assertForbidden();
    }

    public function test_cancel_denied_without_approver_grant(): void
    {
        $order = $this->makeOrder();
        $user = $this->userWithGrant('sales_orders', 'encoder'); // encoder, not approver

        $this->actingAs($user)->delete("/sales-orders/{$order->id}")->assertForbidden();
    }

    public function test_cancel_allowed_with_approver_grant(): void
    {
        $order = $this->makeOrder();
        $user = $this->userWithGrant('sales_orders', 'approver');

        $response = $this->actingAs($user)->delete("/sales-orders/{$order->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
