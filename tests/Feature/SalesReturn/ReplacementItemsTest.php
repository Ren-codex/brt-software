<?php

namespace Tests\Feature\SalesReturn;

use App\Models\Account;
use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\InventoryStocks;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ListStatus;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceivedItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturnReplacement;
use App\Models\User;
use App\Services\Modules\SalesOrderClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReplacementItemsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $returnProduct;
    private Product $replacementProduct;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        foreach ([
            'sales-return-approval', 'sales-returned', 'partially-returned',
            'paid', 'cancelled', 'closed', 'for-payment', 'unpaid', 'partially-paid', 'pending',
        ] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name'        => ucwords(str_replace('-', ' ', $slug)),
                'text_color'  => '#fff',
                'bg_color'    => '#333',
            ]);
        }

        $unitId  = \DB::table('list_units')->insertGetId(['name' => 'Sack', 'created_at' => now(), 'updated_at' => now()]);
        $brandId = \DB::table('list_brands')->insertGetId(['name' => 'Brand', 'created_at' => now(), 'updated_at' => now()]);

        $this->returnProduct = Product::create([
            'code' => 'RETURN-' . uniqid(), 'weight' => 50, 'unit_id' => $unitId, 'brand_id' => $brandId, 'is_active' => true,
        ]);
        $this->replacementProduct = Product::create([
            'code' => 'REPLACE-' . uniqid(), 'weight' => 50, 'unit_id' => $unitId, 'brand_id' => $brandId, 'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name'           => 'Test Customer',
            'address'        => 'Test Address',
            'contact_number' => '09000000001',
            'added_by_id'    => $this->user->id,
            'is_active'      => true,
            'is_regular'     => false,
            'is_blacklisted' => false,
        ]);
    }

    private function makeStock(Product $product, string $batchCode, float $unitCost, int $qty): InventoryStocks
    {
        $supplierId = \DB::table('list_suppliers')->insertGetId([
            'name'           => 'Supplier-' . $batchCode,
            'address'        => 'Addr',
            'contact_person' => 'Person',
            'contact_number' => '09000000000',
            'email'          => 'x@test.com',
            'tin'            => '000-000-000',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $statusId = ListStatus::where('slug', 'paid')->first()->id;

        $poId = \DB::table('purchase_orders')->insertGetId([
            'supplier_id'   => $supplierId,
            'status_id'     => $statusId,
            'created_by_id' => $this->user->id,
            'po_date'       => now()->toDateString(),
            'total_amount'  => ($qty + 10) * $unitCost,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $poItemId = \DB::table('purchase_order_items')->insertGetId([
            'po_id'      => $poId,
            'product_id' => $product->id,
            'quantity'   => $qty + 10,
            'unit_cost'  => $unitCost,
            'total_cost' => ($qty + 10) * $unitCost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rsId = \DB::table('received_stocks')->insertGetId([
            'po_id'         => $poId,
            'supplier_id'   => $supplierId,
            'received_date' => now()->toDateString(),
            'received_no'   => 'RS-' . $batchCode,
            'payment_mode'  => 'cash',
            'amount_paid'   => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $ri = ReceivedItem::create([
            'received_id' => $rsId,
            'product_id'  => $product->id,
            'quantity'    => $qty + 10,
            'unit_cost'   => $unitCost,
            'total_cost'  => ($qty + 10) * $unitCost,
            'po_item_id'  => $poItemId,
        ]);

        return InventoryStocks::create([
            'received_item_id' => $ri->id,
            'quantity'         => $qty,
            'retail_price'     => $unitCost * 1.6,
            'wholesale_price'  => $unitCost * 1.5,
            'batch_code'       => $batchCode,
        ]);
    }

    private function makeCashSoWithReturn(InventoryStocks $stock, int $qty, float $price): array
    {
        $so = SalesOrder::create([
            'so_number'    => 'SO-REP-' . uniqid(),
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'sales-return-approval')->first()->id,
            'total_amount' => $qty * $price,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        $item = new SalesOrderItem([
            'product_id'        => $this->returnProduct->id,
            'quantity'          => $qty,
            'price'             => $price,
            'price_type'        => 'retail',
            'batch_code'        => $stock->batch_code,
            'discount_per_unit' => 0,
        ]);
        $item->sales_order_id = $so->id;
        $item->save();

        $invoice = ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id'      => ListStatus::where('slug', 'paid')->first()->id,
            'invoice_number' => 'INV-' . $so->so_number,
            'invoice_date'   => now()->toDateString(),
            'amount_due'     => $qty * $price,
            'amount_paid'    => $qty * $price,
            'balance_due'    => 0,
        ]);

        $receipt = Receipt::create([
            'ar_invoice_id'  => $invoice->id,
            'customer_id'    => $this->customer->id,
            'status_id'      => ListStatus::where('slug', 'paid')->first()->id,
            'receipt_number' => 'REC-' . $so->so_number,
            'receipt_type'   => 'payment',
            'receipt_date'   => now()->toDateString(),
            'amount_paid'    => $qty * $price,
            'payment_mode'   => 'cash',
        ]);

        \DB::table('sales_return_items')->insert([
            'sales_order_item_id' => $item->id,
            'source_receipt_id'   => $receipt->id,
            'return_quantity'     => $qty,
            'return_condition'    => 'restockable',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return [$so, $item, $receipt];
    }

    // ─── 1. replacement_inventory_out journal entry ───────────────────────────

    public function test_replacement_items_create_inventory_out_journal_entry(): void
    {
        // Return: 2 units @ ₱1,000 = ₱2,000 return value
        // Replace: 2 units of replacementProduct @ ₱500 cost, ₱1,000 selling price (equal value, no extra)
        $returnStock      = $this->makeStock($this->returnProduct, 'BATCH-RET-001', 500.00, 5);
        $replacementStock = $this->makeStock($this->replacementProduct, 'BATCH-REP-001', 500.00, 5);

        [$so, $item] = $this->makeCashSoWithReturn($returnStock, 2, 1000.00);

        $replacementItems = [[
            'product_id' => $this->replacementProduct->id,
            'quantity'   => 2,
            'price'      => 1000.00, // selling price — equal to return value
        ]];

        app(SalesOrderClass::class)->approve($so->id, [$item->id], $replacementItems);

        $je = JournalEntry::where('entry_type', 'replacement_inventory_out')->first();
        $this->assertNotNull($je, 'A replacement_inventory_out journal entry must be created');

        $lines  = JournalEntryLine::where('journal_entry_id', $je->id)->get();
        $this->assertCount(2, $lines);

        $debit  = $lines->firstWhere('line_type', 'debit');
        $credit = $lines->firstWhere('line_type', 'credit');

        $this->assertEquals('cost_of_goods_sold',
            Account::find($debit->account_id)->slug,
            'Debit must post to COGS');

        $this->assertEquals('rice_inventory',
            Account::find($credit->account_id)->slug,
            'Credit must reduce Rice Inventory');

        // 2 units × ₱500 unit cost = ₱1,000
        $this->assertEquals('1000.00', $debit->amount,  'COGS debit must be ₱1,000');
        $this->assertEquals('1000.00', $credit->amount, 'Inventory credit must be ₱1,000');
    }

    // ─── 2. Replacement records persisted to sales_return_replacements ────────

    public function test_replacement_items_are_persisted_to_database(): void
    {
        $returnStock      = $this->makeStock($this->returnProduct, 'BATCH-RET-002', 400.00, 5);
        $replacementStock = $this->makeStock($this->replacementProduct, 'BATCH-REP-002', 400.00, 5);

        [$so, $item] = $this->makeCashSoWithReturn($returnStock, 2, 800.00);

        $replacementItems = [[
            'product_id' => $this->replacementProduct->id,
            'quantity'   => 2,
            'price'      => 800.00,
        ]];

        app(SalesOrderClass::class)->approve($so->id, [$item->id], $replacementItems);

        $records = SalesReturnReplacement::where('sales_order_id', $so->id)->get();
        $this->assertCount(1, $records, 'One replacement record must be persisted');

        $rec = $records->first();
        $this->assertEquals($this->replacementProduct->id, $rec->product_id);
        $this->assertEquals(2,      $rec->quantity);
        $this->assertEquals(800.00, (float) $rec->price);
        $this->assertEquals(1600.00,(float) $rec->total_value);
        $this->assertNotNull($rec->replaced_at);
        $this->assertEquals($this->user->id, $rec->replaced_by_id);
    }

    // ─── 3. Replacement stock is deducted from inventory ─────────────────────

    public function test_replacement_stock_is_deducted_from_inventory(): void
    {
        $returnStock      = $this->makeStock($this->returnProduct, 'BATCH-RET-003', 400.00, 5);
        $replacementStock = $this->makeStock($this->replacementProduct, 'BATCH-REP-003', 400.00, 10);

        [$so, $item] = $this->makeCashSoWithReturn($returnStock, 2, 800.00);

        $replacementItems = [[
            'product_id' => $this->replacementProduct->id,
            'quantity'   => 3,
            'price'      => 800.00,
        ]];

        app(SalesOrderClass::class)->approve($so->id, [$item->id], $replacementItems);

        // 10 replacement units - 3 issued = 7
        $this->assertEquals(7, $replacementStock->fresh()->quantity,
            'Replacement stock must be deducted by 3 (10 → 7)');
    }

    // ─── 4. Undervalue replacement is rejected ────────────────────────────────

    public function test_undervalue_replacement_is_rejected(): void
    {
        $returnStock      = $this->makeStock($this->returnProduct, 'BATCH-RET-004', 400.00, 5);
        $replacementStock = $this->makeStock($this->replacementProduct, 'BATCH-REP-004', 400.00, 5);

        [$so, $item] = $this->makeCashSoWithReturn($returnStock, 2, 1000.00); // return value = ₱2,000

        // Replacement total = ₱1,500 — less than ₱2,000 return value
        $replacementItems = [[
            'product_id' => $this->replacementProduct->id,
            'quantity'   => 2,
            'price'      => 750.00,
        ]];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must be equal to or greater than the return value');

        app(SalesOrderClass::class)->approve($so->id, [$item->id], $replacementItems);
    }

    // ─── 5. Extra receipt + JE when replacement > return value ───────────────

    public function test_extra_receipt_and_journal_entry_created_when_replacement_exceeds_return_value(): void
    {
        $returnStock      = $this->makeStock($this->returnProduct, 'BATCH-RET-005', 400.00, 5);
        $replacementStock = $this->makeStock($this->replacementProduct, 'BATCH-REP-005', 400.00, 5);

        // Return value = 2 × ₱1,000 = ₱2,000
        [$so, $item, $sourceReceipt] = $this->makeCashSoWithReturn($returnStock, 2, 1000.00);

        // Replacement = 2 × ₱1,500 = ₱3,000 → ₱1,000 extra
        $replacementItems = [[
            'product_id' => $this->replacementProduct->id,
            'quantity'   => 2,
            'price'      => 1500.00,
        ]];

        app(SalesOrderClass::class)->approve($so->id, [$item->id], $replacementItems);

        $invoice = ArInvoice::where('sales_order_id', $so->id)->first();

        // Extra receipt for ₱1,000 difference must exist
        $extraReceipts = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('receipt_type', 'payment')
            ->where('amount_paid', 1000.00)
            ->get();
        $this->assertCount(1, $extraReceipts, 'An extra receipt for ₱1,000 must be created');

        // A receipt_collection journal entry must exist for the extra receipt
        $je = JournalEntry::where('entry_type', 'receipt_collection')
            ->where('source_type', Receipt::class)
            ->where('source_id', $extraReceipts->first()->id)
            ->first();
        $this->assertNotNull($je, 'A receipt_collection JE must exist for the extra receipt');

        // And the replacement_inventory_out JE must also exist
        $this->assertNotNull(
            JournalEntry::where('entry_type', 'replacement_inventory_out')->first(),
            'replacement_inventory_out JE must exist alongside the extra-charge JE'
        );
    }

    // ─── 6. No replacement JE when no replacement items provided ─────────────

    public function test_no_replacement_journal_entry_when_no_replacement_items(): void
    {
        $returnStock = $this->makeStock($this->returnProduct, 'BATCH-RET-006', 400.00, 5);
        [$so, $item] = $this->makeCashSoWithReturn($returnStock, 2, 800.00);

        app(SalesOrderClass::class)->approve($so->id, [$item->id], []);

        $this->assertNull(
            JournalEntry::where('entry_type', 'replacement_inventory_out')->first(),
            'No replacement JE should exist when no replacement items are provided'
        );
    }
}
