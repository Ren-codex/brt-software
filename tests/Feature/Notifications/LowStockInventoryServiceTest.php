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
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\Modules\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LowStockInventoryServiceTest extends TestCase
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

    private function makeProductWithStock(int $quantity, int $minimumStock): array
    {
        static $seq = 0;
        $seq++;

        $brand = ListBrand::create(['name' => 'Brand'.$seq]);
        $unit = ListUnit::create(['name' => 'pcs']);
        $supplier = ListSupplier::create([
            'name' => 'Supplier'.$seq,
            'address' => 'Address '.$seq,
            'contact_person' => 'Contact '.$seq,
            'contact_number' => '123456789',
            'email' => 'supplier'.$seq.'@example.com',
            'tin' => 'TIN'.$seq,
        ]);
        $status = ListStatus::create([
            'name' => 'Pending',
            'slug' => 'pending',
            'text_color' => '#000000',
            'bg_color' => '#FFFFFF',
        ]);
        $product = Product::create([
            'brand_id' => $brand->id,
            'pack_size' => '100mg',
            'unit_id' => $unit->id,
            'is_active' => true,
            'minimum_stock' => $minimumStock,
        ]);

        // Create a minimal PurchaseOrder
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_date' => now()->toDateString(),
            'total_amount' => $quantity * 10,
            'status_id' => $status->id,
            'created_by_id' => $this->admin->id,
        ]);

        // Create a PurchaseOrderItem
        $poItem = PurchaseOrderItem::create([
            'po_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => 10,
            'total_cost' => $quantity * 10,
        ]);

        $received = ReceivedStock::create([
            'po_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_date' => now()->toDateString(),
            'received_no' => 'RS-TEST-'.$seq,
            'received_by_id' => $this->admin->id,
        ]);
        $item = ReceivedItem::create([
            'received_id' => $received->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_cost' => 10,
            'total_cost' => $quantity * 10,
            'po_item_id' => $poItem->id,
        ]);
        InventoryStocks::create([
            'received_item_id' => $item->id,
            'quantity' => $quantity,
            'batch_code' => 'BC-TEST-'.$seq,
        ]);

        return [$product, $quantity];
    }

    public function test_deduct_stock_fires_notification_on_minimum_crossing(): void
    {
        Notification::fake();

        [$product] = $this->makeProductWithStock(15, 10);

        // Deduct 10 — stock goes from 15 to 5, crossing below minimum of 10
        app(InventoryService::class)->deductStock($product->id, 10, 'Test sale');

        Notification::assertSentTo($this->admin, LowStockNotification::class);
    }

    public function test_deduct_stock_does_not_fire_when_stock_stays_above_minimum(): void
    {
        Notification::fake();

        [$product] = $this->makeProductWithStock(20, 10);

        // Deduct 5 — stock goes from 20 to 15, still above minimum of 10
        app(InventoryService::class)->deductStock($product->id, 5, 'Test sale');

        Notification::assertNothingSent();
    }

    public function test_deduct_stock_does_not_fire_when_already_below_minimum(): void
    {
        Notification::fake();

        [$product] = $this->makeProductWithStock(5, 10);

        // Already below minimum — deducting more does not re-notify
        app(InventoryService::class)->deductStock($product->id, 2, 'Test sale');

        Notification::assertNothingSent();
    }

    public function test_record_loss_fires_notification_on_minimum_crossing(): void
    {
        Notification::fake();

        [$product] = $this->makeProductWithStock(15, 10);

        app(InventoryService::class)->recordLossOrDamage($product->id, 10, 'Damage', null, 'damage');

        Notification::assertSentTo($this->admin, LowStockNotification::class);
    }
}
