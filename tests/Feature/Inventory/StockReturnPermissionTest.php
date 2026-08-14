<?php

namespace Tests\Feature\Inventory;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\RolePermission;
use App\Models\StockReturn;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReturnPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
    }

    private function warehouseManagerWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'inventory')->firstOrFail();
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeStockReturn(): StockReturn
    {
        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Test Supplier', 'address' => 'Test Address', 'contact_person' => 'Test Person',
            'contact_number' => '09000000000', 'email' => 'supplier@test.com', 'tin' => '000-000-000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 1000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $supplierId, 'created_by_id' => User::factory()->create()->id,
        ]);

        return StockReturn::create([
            'po_id' => $po->id, 'reason' => 'Damaged in transit',
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'created_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->getJson('/stock-returns')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('stock_returns', 'view');

        $this->actingAs($user)->getJson('/stock-returns')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('stock_returns', 'view');

        $this->actingAs($user)->postJson('/stock-returns', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('stock_returns', 'encoder');

        $response = $this->actingAs($user)->postJson('/stock-returns', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_approve_denied_without_approver_grant(): void
    {
        $return = $this->makeStockReturn();
        $user = $this->warehouseManagerWithGrant('stock_returns', 'encoder');

        $this->actingAs($user)
            ->postJson("/stock-returns/{$return->id}/approve", ['status' => 'approved'])
            ->assertForbidden();
    }

    public function test_reject_denied_without_approver_grant(): void
    {
        // 'reject' shares the same approve() action (status: disapproved) — same gate.
        $return = $this->makeStockReturn();
        $user = $this->warehouseManagerWithGrant('stock_returns', 'encoder');

        $this->actingAs($user)
            ->postJson("/stock-returns/{$return->id}/approve", ['status' => 'disapproved'])
            ->assertForbidden();
    }

    public function test_approve_allowed_with_approver_grant(): void
    {
        $return = $this->makeStockReturn();
        $user = $this->warehouseManagerWithGrant('stock_returns', 'approver');

        $response = $this->actingAs($user)
            ->postJson("/stock-returns/{$return->id}/approve", ['status' => 'approved']);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_receive_item_denied_without_approver_grant(): void
    {
        $return = $this->makeStockReturn();
        $user = $this->warehouseManagerWithGrant('stock_returns', 'encoder');

        $this->actingAs($user)
            ->postJson("/stock-returns/{$return->id}/items/1/receive", ['replaced_quantity' => 1, 'loss_quantity' => 0])
            ->assertForbidden();
    }
}
