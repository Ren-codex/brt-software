<?php

namespace Tests\Feature\Reports;

use App\Models\InventoryStocks;
use App\Models\ListBrand;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivedItem;
use App\Models\ReceivedStock;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Margin now treats the cost of a sold unit as unit cost + its per-unit tracking
 * expense: profit = sales − (unit cost + tracking expense) × quantity. The unit
 * cost itself is unchanged; tracking expense is only added on the cost side when
 * working out profit. This is the shared cost basis behind both the Dashboard
 * margin and the Revenue Report.
 */
class MarginIncludesTrackingExpenseTest extends TestCase
{
    use RefreshDatabase;

    /** Build one sold line drawn from a received batch, and return its margin. */
    private function marginFor(float $unitCost, ?float $trackingExpense, int $soldQty, float $price, string $batch): float
    {
        $brand = ListBrand::firstOrCreate(['name' => 'Jasmine Rice'], ['is_active' => 1]);
        $unit = ListUnit::firstOrCreate(['name' => 'Kg'], ['is_active' => 1]);
        $product = Product::create([
            'code' => 'JR-'.uniqid(), 'brand_id' => $brand->id, 'unit_id' => $unit->id,
            'weight' => 50, 'is_active' => 1, 'minimum_stock' => 1,
        ]);

        $user = User::factory()->create();
        $status = ListStatus::firstOrCreate(['slug' => 'pending'], ['name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333']);
        $supplierId = DB::table('list_suppliers')->insertGetId([
            'name' => 'Supplier '.uniqid(), 'address' => 'A', 'contact_person' => 'P',
            'contact_number' => '0900', 'email' => uniqid().'@t.com', 'tin' => '000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $po = PurchaseOrder::create([
            'po_date' => now()->toDateString(), 'total_amount' => $unitCost * 20,
            'status_id' => $status->id, 'supplier_id' => $supplierId, 'created_by_id' => $user->id,
        ]);
        $poItem = PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $product->id,
            'quantity' => 20, 'unit_cost' => $unitCost, 'total_cost' => $unitCost * 20,
        ]);
        $received = ReceivedStock::create([
            'po_id' => $po->id, 'supplier_id' => $supplierId, 'received_date' => now(),
            'received_no' => 'RS-'.uniqid(), 'payment_mode' => 'Credit', 'amount_paid' => 0,
        ]);
        $ri = ReceivedItem::create([
            'received_id' => $received->id, 'product_id' => $product->id, 'po_item_id' => $poItem->id,
            'quantity' => 20, 'unit_cost' => $unitCost, 'total_cost' => $unitCost * 20,
            'tracking_expense' => $trackingExpense,
        ]);
        InventoryStocks::create([
            'received_item_id' => $ri->id, 'product_id' => $product->id,
            'batch_code' => $batch, 'quantity' => 20,
        ]);

        $so = SalesOrder::create([
            'so_number' => 'SO-'.uniqid(), 'order_date' => now()->toDateString(),
            'status_id' => $status->id, 'total_amount' => $price * $soldQty, 'total_discount' => 0,
            'added_by_id' => $user->id, 'payment_mode' => 'Cash',
        ]);
        SalesOrderItem::create([
            'sales_order_id' => $so->id, 'product_id' => $product->id, 'batch_code' => $batch,
            'quantity' => $soldQty, 'price' => $price, 'discount_per_unit' => 0, 'price_type' => 'retail',
        ]);

        // The exact cost expression both the Dashboard and the Revenue Report use.
        return (float) DB::table('sales_order_items as soi')
            ->join('inventory_stocks as inv', 'soi.batch_code', '=', 'inv.batch_code')
            ->join('received_items as ri', function ($j) {
                $j->on('inv.received_item_id', '=', 'ri.id')->on('soi.product_id', '=', 'ri.product_id');
            })
            ->where('soi.batch_code', $batch)
            ->selectRaw('SUM(soi.quantity * soi.price) - SUM(soi.quantity * (ri.unit_cost + COALESCE(ri.tracking_expense, 0))) as profit')
            ->value('profit');
    }

    public function test_tracking_expense_is_added_to_the_cost_of_each_unit(): void
    {
        // 10 sold @ 700, unit cost 500 + tracking 50 = 550 landed cost.
        // profit = 10*700 - 10*550 = 7000 - 5500 = 1500.
        $this->assertEquals(1500.0, $this->marginFor(500, 50, 10, 700, 'B-WITH'));
    }

    public function test_without_a_tracking_expense_the_margin_is_unchanged(): void
    {
        // Null tracking → COALESCE 0 → profit = 10*700 - 10*500 = 2000 (old behaviour).
        $this->assertEquals(2000.0, $this->marginFor(500, null, 10, 700, 'B-NONE'));
    }
}
