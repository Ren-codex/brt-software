<?php

namespace Tests\Feature\SalesReturn;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ListStatus;
use App\Models\Account;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceivedItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\Modules\SalesOrderClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamagedItemsReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Seed all statuses needed by the return approval flow
        foreach ([
            'sales-return-approval', 'sales-returned', 'partially-returned',
            'paid', 'cancelled', 'closed', 'for-payment',
        ] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'text_color' => '#fff',
                'bg_color' => '#333',
            ]);
        }

        // Lookup tables required by FK constraints
        $unitId  = \DB::table('list_units')->insertGetId(['name' => 'Sack', 'created_at' => now(), 'updated_at' => now()]);
        $brandId = \DB::table('list_brands')->insertGetId(['name' => 'Test Brand', 'created_at' => now(), 'updated_at' => now()]);

        $this->product = Product::create(['pack_size' => 50, 'unit_id' => $unitId, 'brand_id' => $brandId, 'is_active' => true]);
        $this->customer = Customer::create([
            'name' => 'Test Customer', 'address' => 'Test Address',
            'contact_number' => '09000000001', 'added_by_id' => $this->user->id,
            'is_active' => true, 'is_regular' => false, 'is_blacklisted' => false,
        ]);
    }

    private function makeInventoryStock(string $batchCode, float $unitCost, int $quantity): InventoryStocks
    {
        // Build the full FK chain: supplier → PO → PO item → received stock → received item → inventory stock
        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'Test Supplier ' . $batchCode, 'address' => 'Test Address',
            'contact_person' => 'Test Person', 'contact_number' => '09000000000',
            'email' => 'supplier@test.com', 'tin' => '000-000-000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $statusId = ListStatus::where('slug', 'paid')->first()->id;

        $poId = \DB::table('purchase_orders')->insertGetId([
            'supplier_id' => $supplierId, 'status_id' => $statusId,
            'created_by_id' => $this->user->id,
            'po_date' => now()->toDateString(),
            'total_amount' => ($quantity + 10) * $unitCost,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $poItemId = \DB::table('purchase_order_items')->insertGetId([
            'po_id' => $poId, 'product_id' => $this->product->id,
            'quantity' => $quantity + 10, 'unit_cost' => $unitCost,
            'total_cost' => ($quantity + 10) * $unitCost,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $receivedStockId = \DB::table('received_stocks')->insertGetId([
            'po_id' => $poId, 'supplier_id' => $supplierId,
            'received_date' => now()->toDateString(), 'received_no' => 'RS-' . $batchCode,
            'payment_mode' => 'cash', 'amount_paid' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $receivedItem = ReceivedItem::create([
            'received_id' => $receivedStockId, 'product_id' => $this->product->id,
            'quantity' => $quantity + 10, 'unit_cost' => $unitCost,
            'total_cost' => ($quantity + 10) * $unitCost, 'po_item_id' => $poItemId,
        ]);

        return InventoryStocks::create([
            'received_item_id' => $receivedItem->id,
            'quantity'         => $quantity,
            'retail_price'     => $unitCost * 1.6,
            'wholesale_price'  => $unitCost * 1.5,
            'batch_code'       => $batchCode,
        ]);
    }

    private function makeSalesOrder(string $soNumber, InventoryStocks $stock, int $qty, float $price): array
    {
        $statusId = ListStatus::where('slug', 'sales-return-approval')->first()->id;

        $so = SalesOrder::create([
            'so_number'    => $soNumber,
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => $statusId,
            'total_amount' => $qty * $price,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        $item = new SalesOrderItem([
            'product_id'       => $this->product->id,
            'quantity'         => $qty,
            'price'            => $price,
            'price_type'       => 'retail',
            'batch_code'       => $stock->batch_code,
            'discount_per_unit' => 0,
        ]);
        $item->sales_order_id = $so->id;
        $item->save();

        $invoice = ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id'      => ListStatus::where('slug', 'paid')->first()->id,
            'invoice_number' => 'INV-' . $soNumber,
            'invoice_date'   => now()->toDateString(),
            'amount_paid'    => $qty * $price,
            'balance_due'    => 0,
        ]);

        $receipt = Receipt::create([
            'ar_invoice_id'  => $invoice->id,
            'customer_id'    => $this->customer->id,
            'status_id'      => ListStatus::where('slug', 'paid')->first()->id,
            'receipt_number' => 'REC-' . $soNumber,
            'receipt_type'   => 'payment',
            'receipt_date'   => now()->toDateString(),
            'amount_paid'    => $qty * $price,
            'payment_mode'   => 'cash',
        ]);

        return [$so, $item, $receipt];
    }

    public function test_damaged_return_writes_off_directly_without_phantom_stock_intake(): void
    {
        $stock = $this->makeInventoryStock('BATCH-DMG-001', 500.00, 5);
        [$so, $item, $receipt] = $this->makeSalesOrder('SO-DMG-001', $stock, 2, 800.00);

        \DB::table('sales_return_items')->insert([
            'sales_order_item_id' => $item->id,
            'source_receipt_id'   => $receipt->id,
            'return_quantity'     => 2,
            'return_condition'    => 'damaged',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        /** @var SalesOrderClass $service */
        $service = app(SalesOrderClass::class);
        $service->approve($so->id, [$item->id]);

        // Stock quantity: was 5, damaged 2 deducted = 3
        $this->assertEquals(3, $stock->fresh()->quantity, 'Damaged write-off should deduct 2 from stock (5 → 3)');

        // One damage adjustment, no phantom positive-quantity adjustments
        $damageAdjustments = InventoryAdjustment::where('type', 'damage')->get();
        $this->assertCount(1, $damageAdjustments, 'Exactly one damage adjustment record expected');
        $this->assertEquals(2, $damageAdjustments->first()->previous_quantity - $damageAdjustments->first()->new_quantity,
            'Adjustment should reflect 2-unit deduction');

        $phantomIntake = InventoryAdjustment::where('new_quantity', '>', \DB::raw('previous_quantity'))->count();
        $this->assertEquals(0, $phantomIntake, 'No phantom stock intake record should exist');

        // Journal entry: sales_return_damage_writeoff
        $writeoffEntry = JournalEntry::where('entry_type', 'sales_return_damage_writeoff')->first();
        $this->assertNotNull($writeoffEntry, 'A sales_return_damage_writeoff journal entry must exist');

        $lines = JournalEntryLine::where('journal_entry_id', $writeoffEntry->id)->get();
        $this->assertCount(2, $lines, 'Write-off entry must have 2 lines');

        $debitLine  = $lines->firstWhere('line_type', 'debit');
        $creditLine = $lines->firstWhere('line_type', 'credit');
        $this->assertNotNull($debitLine,  'Debit line must exist');
        $this->assertNotNull($creditLine, 'Credit line must exist');

        $this->assertEquals('loss_on_damaged_goods',
            Account::find($debitLine->account_id)->slug,
            'Debit must post to Loss on Damaged Goods');

        $this->assertEquals('cost_of_goods_sold',
            Account::find($creditLine->account_id)->slug,
            'Credit must reverse Cost of Goods Sold');

        // 2 units × ₱500 unit cost = ₱1,000
        $this->assertEquals('1000.00', $debitLine->amount,  'Debit amount must be ₱1,000');
        $this->assertEquals('1000.00', $creditLine->amount, 'Credit amount must be ₱1,000');

        // SO status updated
        $salesReturnedId = ListStatus::where('slug', 'sales-returned')->first()->id;
        $this->assertEquals($salesReturnedId, $so->fresh()->status_id, 'SO must be marked sales-returned');
    }

    public function test_restockable_return_does_not_create_damage_writeoff_entry(): void
    {
        $stock = $this->makeInventoryStock('BATCH-RST-001', 400.00, 5);
        [$so, $item, $receipt] = $this->makeSalesOrder('SO-RST-001', $stock, 2, 700.00);

        \DB::table('sales_return_items')->insert([
            'sales_order_item_id' => $item->id,
            'source_receipt_id'   => $receipt->id,
            'return_quantity'     => 2,
            'return_condition'    => 'restockable',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        /** @var SalesOrderClass $service */
        $service = app(SalesOrderClass::class);
        $service->approve($so->id, [$item->id]);

        // No damage write-off entry
        $this->assertNull(
            JournalEntry::where('entry_type', 'sales_return_damage_writeoff')->first(),
            'Restockable return must not create a damage write-off entry'
        );

        // Inventory was restored: 5 + 2 = 7
        $this->assertEquals(7, $stock->fresh()->quantity, 'Restockable return should restore stock to 7');

        // Inventory restoration JE exists
        $this->assertNotNull(
            JournalEntry::where('entry_type', 'sales_return_inventory')->first(),
            'Restockable return must create an inventory restoration journal entry'
        );
    }
}
