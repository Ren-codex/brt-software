<?php

namespace Tests\Feature\Inventory;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\ReceivedStock;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivedStockPermissionTest extends TestCase
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

    private function makeReceivedStock(): ReceivedStock
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

        return ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplierId,
            'received_date' => now()->toDateString(), 'received_no' => 'RS-TEST-' . uniqid(),
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->getJson('/received-stocks')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('receiving', 'view');

        $this->actingAs($user)->getJson('/received-stocks')->assertOk();
    }

    public function test_pay_denied_without_encoder_grant(): void
    {
        $rs = $this->makeReceivedStock();
        $user = $this->warehouseManagerWithGrant('receiving', 'view'); // view only, not encoder

        $this->actingAs($user)
            ->post("/received-stocks/{$rs->id}/pay", ['amount' => 100, 'payment_mode' => 'Cash', 'payment_date' => now()->toDateString()])
            ->assertForbidden();
    }

    /**
     * Receiving no longer carries the right to pay — that moved to
     * accounting/accounts_payable so a warehouse manager can take a delivery in
     * without being able to move money.
     */
    public function test_pay_denied_with_only_a_receiving_grant(): void
    {
        $rs = $this->makeReceivedStock();
        $user = $this->warehouseManagerWithGrant('receiving', 'encoder');

        $this->actingAs($user)
            ->post("/received-stocks/{$rs->id}/pay", ['amount' => 100, 'payment_mode' => 'Cash', 'payment_date' => now()->toDateString()])
            ->assertForbidden();
    }

    public function test_pay_allowed_with_an_accounts_payable_grant(): void
    {
        $rs = $this->makeReceivedStock();

        $role = ListRole::firstOrCreate(['name' => 'Payables Clerk'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $accounting = Module::where('key', 'accounting')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id,
            'module_id' => $accounting->id,
            'submodule_id' => $accounting->submodules()->where('key', 'accounts_payable')->firstOrFail()->id,
            'access_level' => 'encoder',
        ]);

        $response = $this->actingAs($user)
            ->post("/received-stocks/{$rs->id}/pay", ['amount' => 100, 'payment_mode' => 'Cash', 'payment_date' => now()->toDateString()]);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $rs = $this->makeReceivedStock();
        $user = $this->warehouseManagerWithGrant('receiving', 'encoder');

        $this->actingAs($user)->delete("/received-stocks/{$rs->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $rs = $this->makeReceivedStock();
        $user = $this->warehouseManagerWithGrant('receiving', 'admin');

        $response = $this->actingAs($user)->delete("/received-stocks/{$rs->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
