<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryStocks;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Module;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivedItem;
use App\Models\ReceivedStock;
use App\Models\RolePermission;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReturnPartialReceiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['pending', 'approved', 'completed', 'replaced', 'loss', 'partial'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucfirst($slug), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function warehouseManagerWithGrant(): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'inventory')->firstOrFail();
        $submoduleId = $module->submodules()->where('key', 'stock_returns')->firstOrFail()->id;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => 'approver',
        ]);

        return $user;
    }

    /**
     * Full chain: supplier -> PO -> PO item (qty 10) -> received stock ->
     * received item (linking the PO item to a product) -> inventory stock
     * (so receiveItem() has something to add replacement quantity back
     * into) -> an approved stock return requesting all 10 units back.
     */
    private function makeApprovedReturnWithItem(int $requestedQty = 10): array
    {
        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Test Supplier', 'address' => 'Test Address', 'contact_person' => 'Test Person',
            'contact_number' => '09000000000', 'email' => 'supplier@test.com', 'tin' => '000-000-000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $unit = ListUnit::create(['name' => 'Sack']);
        $brand = ListBrand::create(['name' => 'Test Brand']);
        $product = Product::create([
            'code' => 'RET10', 'weight' => 10, 'unit_id' => $unit->id,
            'brand_id' => $brand->id, 'is_active' => 1, 'minimum_stock' => 1,
        ]);

        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 5000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $supplierId, 'created_by_id' => User::factory()->create()->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $product->id, 'quantity' => 20,
            'unit_cost' => 500, 'total_cost' => 10000, 'status' => 'pending', 'received_quantity' => 20,
        ]);

        $received = ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplierId, 'received_date' => now()->toDateString(),
            'received_no' => 'RS-TEST-' . uniqid(), 'payment_mode' => 'Cash', 'amount_paid' => 10000,
        ]);

        $receivedItem = ReceivedItem::create([
            'received_id' => $received->id, 'product_id' => $product->id, 'po_item_id' => $poItem->id,
            'quantity' => 20, 'unit_cost' => 500, 'total_cost' => 10000,
        ]);

        $inventoryStock = InventoryStocks::create([
            'batch_code' => 'B-RETTEST', 'received_item_id' => $receivedItem->id,
            'quantity' => 5, 'retail_price' => 600, 'wholesale_price' => 550, 'unit_cost' => 500,
        ]);

        $stockReturn = StockReturn::create([
            'po_id' => $po->id, 'reason' => 'Damaged in transit',
            'status_id' => ListStatus::where('slug', 'approved')->first()->id,
            'created_by_id' => User::factory()->create()->id,
        ]);

        $item = StockReturnItem::create([
            'stock_return_id' => $stockReturn->id, 'po_item_id' => $poItem->id,
            'quantity' => $requestedQty, 'status_id' => ListStatus::where('slug', 'pending')->first()->id,
        ]);

        return compact('stockReturn', 'item', 'inventoryStock');
    }

    /**
     * Reproduces the real bug: receiving only 5 of a requested 10 units
     * used to get tagged 'replaced' (a terminal, "fully done" status) just
     * because *some* replacement quantity was recorded — which then made
     * the stock return's own completion check think every item was
     * resolved, prematurely marking the whole return 'completed' and
     * hiding the Receive Stock button before the remaining 5 ever arrived.
     */
    public function test_partial_receive_does_not_prematurely_complete_the_return(): void
    {
        $user = $this->warehouseManagerWithGrant();
        ['stockReturn' => $stockReturn, 'item' => $item, 'inventoryStock' => $inventoryStock] = $this->makeApprovedReturnWithItem();

        $response = $this->actingAs($user)->postJson(
            "/stock-returns/{$stockReturn->id}/items/{$item->id}/receive",
            ['replaced_quantity' => 5, 'loss_quantity' => 0]
        );

        $response->assertOk();

        $item->refresh();
        $stockReturn->refresh();

        $this->assertEquals('partial', $item->status->slug);
        $this->assertEquals(5, $item->returned_quantity);
        $this->assertEquals(5, $item->replaced_quantity);

        // The whole return must still be 'approved' — not prematurely
        // completed — since only half the requested quantity came back.
        $this->assertEquals('approved', $stockReturn->status->slug);

        $inventoryStock->refresh();
        $this->assertEquals(10, $inventoryStock->quantity); // 5 original + 5 replaced

        // Finish the job: submit the full cumulative quantity (10, not an
        // incremental 5 — the frontend pre-fills the running total).
        $response2 = $this->actingAs($user)->postJson(
            "/stock-returns/{$stockReturn->id}/items/{$item->id}/receive",
            ['replaced_quantity' => 10, 'loss_quantity' => 0]
        );
        $response2->assertOk();

        $item->refresh();
        $stockReturn->refresh();

        $this->assertEquals('replaced', $item->status->slug);
        $this->assertEquals('completed', $stockReturn->status->slug);
    }
}
