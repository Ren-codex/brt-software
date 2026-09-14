<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryAdjustment;
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

/**
 * Closing out a stock return: what the supplier replaced, what they refused to,
 * and what that leaves behind on the purchase order and in the batches.
 *
 * These post over HTTP because the rules live in the controller and service
 * together -- a service-level test would miss the validation either side.
 */
class StockReturnReceiveFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['pending', 'approved', 'disapproved', 'completed', 'replaced', 'loss', 'partial', 'voided'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucfirst($slug), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function approver(): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'inventory')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'stock_returns')->firstOrFail()->id,
            'access_level' => 'approver',
        ]);

        return $user;
    }

    /**
     * A purchase order fully received into two batches, with a stock return
     * raised against it. $approved controls whether the return has already been
     * through approval (and so already deducted stock).
     */
    private function scenario(int $requestedQty = 10, bool $approved = true): array
    {
        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Test Supplier', 'address' => 'Addr', 'contact_person' => 'Person',
            'contact_number' => '09000000000', 'email' => 's@test.com', 'tin' => '000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $product = Product::create([
            'code' => 'RET-FIX', 'weight' => 10,
            'unit_id' => ListUnit::create(['name' => 'Sack'])->id,
            'brand_id' => ListBrand::create(['name' => 'Brand'])->id,
            'is_active' => 1, 'minimum_stock' => 1,
        ]);

        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => 10000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'supplier_id' => $supplierId, 'created_by_id' => User::factory()->create()->id,
        ]);

        // Fully received: quantity and received_quantity match, status 'received'.
        $poItem = PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $product->id, 'quantity' => 20,
            'unit_cost' => 500, 'total_cost' => 10000, 'status' => 'received', 'received_quantity' => 20,
        ]);

        $received = ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplierId, 'received_date' => now()->toDateString(),
            'received_no' => 'RS-'.uniqid(), 'payment_mode' => 'Cash', 'amount_paid' => 10000,
        ]);
        $receivedItem = ReceivedItem::create([
            'received_id' => $received->id, 'product_id' => $product->id, 'po_item_id' => $poItem->id,
            'quantity' => 20, 'unit_cost' => 500, 'total_cost' => 10000,
        ]);

        // Two batches. The first is the one a lowest-id lookup would grab.
        $first = InventoryStocks::create([
            'batch_code' => 'B-FIRST', 'received_item_id' => $receivedItem->id,
            'quantity' => 4, 'retail_price' => 600, 'wholesale_price' => 550, 'unit_cost' => 500,
        ]);
        $second = InventoryStocks::create([
            'batch_code' => 'B-SECOND', 'received_item_id' => $receivedItem->id,
            'quantity' => 16, 'retail_price' => 600, 'wholesale_price' => 550, 'unit_cost' => 500,
        ]);

        $stockReturn = StockReturn::create([
            'po_id' => $po->id, 'reason' => 'Damaged',
            'status_id' => ListStatus::where('slug', $approved ? 'approved' : 'pending')->first()->id,
            'created_by_id' => User::factory()->create()->id,
        ]);
        $item = StockReturnItem::create([
            'stock_return_id' => $stockReturn->id, 'po_item_id' => $poItem->id,
            'quantity' => $requestedQty, 'status_id' => ListStatus::where('slug', 'pending')->first()->id,
        ]);

        return compact('stockReturn', 'item', 'poItem', 'first', 'second', 'product');
    }

    private function receive(User $user, StockReturn $r, StockReturnItem $i, array $payload)
    {
        return $this->actingAs($user)->postJson("/stock-returns/{$r->id}/items/{$i->id}/receive", $payload);
    }

    public function test_a_loss_closes_an_item_the_supplier_will_not_replace(): void
    {
        // The whole point: without a loss the item could never be finished, and
        // the return sat open forever waiting for goods that were never coming.
        $user = $this->approver();
        ['stockReturn' => $r, 'item' => $i] = $this->scenario(10);

        $this->receive($user, $r, $i, ['replaced_quantity' => 0, 'loss_quantity' => 10])->assertOk();

        $i->refresh();
        $r->refresh();

        $this->assertEquals('loss', $i->status->slug);
        $this->assertEquals(10, $i->loss_quantity);
        $this->assertEquals('completed', $r->status->slug);
    }

    public function test_a_split_of_replacement_and_loss_closes_the_item(): void
    {
        $user = $this->approver();
        ['stockReturn' => $r, 'item' => $i] = $this->scenario(10);

        $this->receive($user, $r, $i, ['replaced_quantity' => 6, 'loss_quantity' => 4])->assertOk();

        $i->refresh();
        $this->assertEquals(6, $i->replaced_quantity);
        $this->assertEquals(4, $i->loss_quantity);
        $this->assertEquals(10, $i->returned_quantity);
        // Something was replaced, so the item reads as replaced rather than lost.
        $this->assertEquals('replaced', $i->status->slug);
        $this->assertEquals('completed', $r->fresh()->status->slug);
    }

    public function test_a_half_received_item_reads_as_partial_not_pending(): void
    {
        // 'pending' made a half-received item look untouched.
        $user = $this->approver();
        ['stockReturn' => $r, 'item' => $i] = $this->scenario(10);

        $this->receive($user, $r, $i, ['replaced_quantity' => 3, 'loss_quantity' => 2])->assertOk();

        $this->assertEquals('partial', $i->fresh()->status->slug);
        $this->assertEquals('approved', $r->fresh()->status->slug);
    }

    public function test_receiving_nothing_is_refused(): void
    {
        $user = $this->approver();
        ['stockReturn' => $r, 'item' => $i] = $this->scenario(10);

        $this->receive($user, $r, $i, ['replaced_quantity' => 0, 'loss_quantity' => 0])->assertStatus(422);

        $i->refresh();
        $this->assertEquals(0, $i->returned_quantity);
        $this->assertNull($i->received_at);
        $this->assertSame(0, \DB::table('stock_return_logs')->where('stock_return_id', $r->id)->count());
    }

    public function test_replacement_and_loss_together_cannot_exceed_what_went_back(): void
    {
        $user = $this->approver();
        ['stockReturn' => $r, 'item' => $i] = $this->scenario(10);

        $this->receive($user, $r, $i, ['replaced_quantity' => 7, 'loss_quantity' => 7])->assertStatus(422);

        $this->assertEquals(0, $i->fresh()->returned_quantity);
    }

    public function test_approving_a_return_makes_the_purchase_order_line_not_fully_received(): void
    {
        $user = $this->approver();
        ['stockReturn' => $r, 'poItem' => $poItem] = $this->scenario(10, approved: false);

        $this->actingAs($user)
            ->postJson("/stock-returns/{$r->id}/approve", ['status' => 'approved'])
            ->assertOk();

        $poItem->refresh();
        $this->assertEquals(10, $poItem->received_quantity);
        // 10 of 20 left: the line cannot still claim it was received in full.
        $this->assertEquals('pending', $poItem->status);
    }

    public function test_a_replacement_goes_back_into_the_batch_the_return_took_it_from(): void
    {
        $user = $this->approver();
        ['stockReturn' => $r, 'item' => $i, 'first' => $first, 'second' => $second]
            = $this->scenario(10, approved: false);

        // Approval drains the 4 in the first batch, then takes 6 from the second.
        $this->actingAs($user)
            ->postJson("/stock-returns/{$r->id}/approve", ['status' => 'approved'])
            ->assertOk();

        $this->assertEquals(0, $first->fresh()->quantity);
        $this->assertEquals(10, $second->fresh()->quantity);

        $this->receive($user, $r, $i, ['replaced_quantity' => 10, 'loss_quantity' => 0])->assertOk();

        // The replacement belongs with the stock that actually went back, not in
        // the emptied first batch just because it has the lowest id.
        $this->assertEquals(20, $second->fresh()->quantity);
        $this->assertEquals(0, $first->fresh()->quantity);

        $this->assertDatabaseHas('inventory_adjustments', [
            'inventory_stocks_id' => $second->id,
            'type' => 'return_replacement',
        ]);
        $this->assertSame(0, InventoryAdjustment::where('inventory_stocks_id', $first->id)
            ->where('type', 'return_replacement')->count());
    }
}
