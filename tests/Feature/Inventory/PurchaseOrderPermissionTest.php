<?php

namespace Tests\Feature\Inventory;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderPermissionTest extends TestCase
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

    /**
     * Every fixture holds the Warehouse Manager role (satisfies the existing
     * role:Administrator,Warehouse Manager gate) and varies only the new
     * fine-grained grant — matching how this plan actually changes behavior.
     */
    private function warehouseManagerWithGrant(?string $submoduleKey, ?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'inventory')->firstOrFail();
            $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submoduleId, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makePurchaseOrder(): PurchaseOrder
    {
        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Test Supplier', 'address' => 'Test Address', 'contact_person' => 'Test Person',
            'contact_number' => '09000000000', 'email' => 'supplier@test.com', 'tin' => '000-000-000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 1000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $supplierId, 'created_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('receiving', 'view');

        $this->actingAs($user)->getJson('/purchase-orders?option=list')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->getJson('/purchase-orders?option=list')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->post('/purchase-orders', [])->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'encoder');

        $response = $this->actingAs($user)->post('/purchase-orders', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_void_denied_without_void_grant(): void
    {
        $po = $this->makePurchaseOrder();
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'approver');

        $this->actingAs($user)->patch("/purchase-orders/{$po->id}/void")->assertForbidden();
    }

    public function test_void_allowed_with_void_grant(): void
    {
        $po = $this->makePurchaseOrder();
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'void');

        $response = $this->actingAs($user)->patch("/purchase-orders/{$po->id}/void");

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_update_status_denied_without_approver_grant(): void
    {
        $po = $this->makePurchaseOrder();
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'encoder');

        $this->actingAs($user)->put("/purchase-orders/{$po->id}/status")->assertForbidden();
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $po = $this->makePurchaseOrder();
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'approver');

        $this->actingAs($user)->delete("/purchase-orders/{$po->id}")->assertForbidden();
    }

    public function test_still_blocked_by_the_existing_role_gate(): void
    {
        // A user with the right permission grant but NOT Administrator/Warehouse
        // Manager must still be blocked — the outer role gate is untouched.
        $role = ListRole::create(['name' => 'Random Role', 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'inventory')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id, 'submodule_id' => null, 'access_level' => 'admin',
        ]);

        $this->actingAs($user)->getJson('/purchase-orders?option=list')->assertForbidden();
    }
}
