<?php

namespace Tests\Feature\Notifications;

use App\Models\InventoryStocks;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivedItem;
use App\Models\ReceivedStock;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\System\PurchaseOrder\StockReturnClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowStockStockReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => '', 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole->id, ['added_by_id' => $this->admin->id]);
        $this->actingAs($this->admin);
    }

    public function test_approving_stock_return_fires_notification_when_stock_crosses_minimum(): void
    {
        Notification::fake();

        // Set up product with stock at 15, minimum at 10
        $brand = ListBrand::create(['name' => 'TestBrand']);
        $unit = ListUnit::create(['name' => 'pcs']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'pack_size' => '100mg',
            'unit_id' => $unit->id,
            'is_active' => true,
            'minimum_stock' => 10,
        ]);

        ListStatus::create([
            'name' => 'Pending',
            'slug' => 'pending',
            'text_color' => '#000000',
            'bg_color' => '#ffff00',
        ]);
        ListStatus::create([
            'name' => 'Approved',
            'slug' => 'approved',
            'text_color' => '#000000',
            'bg_color' => '#00ff00',
        ]);
        ListStatus::create([
            'name' => 'Disapproved',
            'slug' => 'disapproved',
            'text_color' => '#ffffff',
            'bg_color' => '#ff0000',
        ]);

        $pendingStatus = ListStatus::where('slug', 'pending')->first();
        $approvedStatus = ListStatus::where('slug', 'approved')->first();

        $supplier = ListSupplier::create([
            'name' => 'Test Supplier',
            'address' => '123 Test St',
            'contact_person' => 'John Doe',
            'contact_number' => '555-1234',
            'email' => 'supplier@test.com',
            'tin' => '123456789',
        ]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-001',
            'supplier_id' => $supplier->id,
            'status_id' => $pendingStatus->id,
            'po_date' => now()->toDateString(),
            'total_amount' => 150,
            'created_by_id' => $this->admin->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'po_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'received_quantity' => 15,
            'unit_cost' => 10,
            'total_cost' => 150,
        ]);

        $received = ReceivedStock::create([
            'po_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_date' => now()->toDateString(),
            'received_no' => 'RS-001',
            'received_by_id' => $this->admin->id,
        ]);

        $recvItem = ReceivedItem::create([
            'received_id' => $received->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'unit_cost' => 10,
            'total_cost' => 150,
            'po_item_id' => $poItem->id,
        ]);

        InventoryStocks::create([
            'batch_code' => 'BATCH-001',
            'received_item_id' => $recvItem->id,
            'quantity' => 15,
        ]);

        $stockReturn = StockReturn::create([
            'stock_return_no' => 'SR-001',
            'po_id' => $po->id,
            'status_id' => $pendingStatus->id,
            'reason' => 'Defective items',
            'created_by_id' => $this->admin->id,
        ]);

        StockReturnItem::create([
            'stock_return_id' => $stockReturn->id,
            'po_item_id' => $poItem->id,
            'quantity' => 10,
            'status_id' => $pendingStatus->id,
        ]);

        $request = Request::create('/stock-returns/'.$stockReturn->id.'/approve', 'PATCH', ['status' => 'approved']);

        app(StockReturnClass::class)->approve($request, $stockReturn->id);

        Notification::assertSentTo($this->admin, LowStockNotification::class);
    }
}
