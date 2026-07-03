<?php

namespace Tests\Feature\SalesReturn;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\InventoryAdjustment;
use App\Models\InventoryStocks;
use App\Models\JournalEntry;
use App\Models\ListStatus;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceivedItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturnHistory;
use App\Models\SalesReturnReplacement;
use App\Models\User;
use App\Services\Modules\SalesOrderClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that every DB table is correctly populated for every return scenario.
 * Each test isolates one scenario and asserts every table that should be touched.
 */
class DataRecordingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Product $productB;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        foreach ([
            'sales-return-approval', 'sales-returned', 'partially-returned',
            'paid', 'cancelled', 'closed', 'for-payment', 'unpaid',
            'partially-paid', 'pending', 'liquidated', 'approved',
        ] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name'       => ucwords(str_replace('-', ' ', $slug)),
                'text_color' => '#fff',
                'bg_color'   => '#333',
            ]);
        }

        $unitId  = \DB::table('list_units')->insertGetId(['name' => 'Sack', 'created_at' => now(), 'updated_at' => now()]);
        $brandId = \DB::table('list_brands')->insertGetId(['name' => 'Brand', 'created_at' => now(), 'updated_at' => now()]);

        $this->product  = Product::create(['code' => 'P1-' . uniqid(), 'weight' => 50, 'unit_id' => $unitId, 'brand_id' => $brandId, 'is_active' => true]);
        $this->productB = Product::create(['code' => 'P2-' . uniqid(), 'weight' => 50, 'unit_id' => $unitId, 'brand_id' => $brandId, 'is_active' => true]);

        $this->customer = Customer::create([
            'name' => 'Test Customer', 'address' => 'Addr', 'contact_number' => '09000000001',
            'added_by_id' => $this->user->id, 'is_active' => true, 'is_regular' => false, 'is_blacklisted' => false,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function makeStock(Product $product, string $batch, float $cost, int $qty): InventoryStocks
    {
        $supId = \DB::table('list_suppliers')->insertGetId([
            'name' => 'S-' . $batch, 'address' => 'A', 'contact_person' => 'P',
            'contact_number' => '0', 'email' => 'x@x.com', 'tin' => '0',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $stId = ListStatus::where('slug', 'paid')->first()->id;
        $poId = \DB::table('purchase_orders')->insertGetId([
            'supplier_id' => $supId, 'status_id' => $stId, 'created_by_id' => $this->user->id,
            'po_date' => now()->toDateString(), 'total_amount' => ($qty + 10) * $cost,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $piId = \DB::table('purchase_order_items')->insertGetId([
            'po_id' => $poId, 'product_id' => $product->id,
            'quantity' => $qty + 10, 'unit_cost' => $cost, 'total_cost' => ($qty + 10) * $cost,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $rsId = \DB::table('received_stocks')->insertGetId([
            'po_id' => $poId, 'supplier_id' => $supId, 'received_date' => now()->toDateString(),
            'received_no' => 'RS-' . $batch, 'payment_mode' => 'cash', 'amount_paid' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ri = ReceivedItem::create([
            'received_id' => $rsId, 'product_id' => $product->id,
            'quantity' => $qty + 10, 'unit_cost' => $cost, 'total_cost' => ($qty + 10) * $cost,
            'po_item_id' => $piId,
        ]);
        return InventoryStocks::create([
            'received_item_id' => $ri->id,
            'quantity'         => $qty,
            'retail_price'     => $cost * 1.6,
            'wholesale_price'  => $cost * 1.5,
            'batch_code'       => $batch,
        ]);
    }

    private function makeSo(string $mode, float $total): SalesOrder
    {
        return SalesOrder::create([
            'so_number'    => 'SO-DR-' . uniqid(),
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'sales-return-approval')->first()->id,
            'total_amount' => $total,
            'payment_mode' => $mode,
            'added_by_id'  => $this->user->id,
        ]);
    }

    private function addItem(SalesOrder $so, InventoryStocks $stock, int $qty, float $price, int $returnedQty = 0): SalesOrderItem
    {
        $item = new SalesOrderItem([
            'product_id'        => $stock->receivedItem->product_id,
            'quantity'          => $qty,
            'returned_quantity' => $returnedQty,
            'price'             => $price,
            'price_type'        => 'retail',
            'batch_code'        => $stock->batch_code,
            'discount_per_unit' => 0,
        ]);
        $item->sales_order_id = $so->id;
        $item->save();
        return $item;
    }

    private function makeInvoice(SalesOrder $so, float $due, float $paid, float $balance, string $slug = 'paid'): ArInvoice
    {
        return ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id'      => ListStatus::where('slug', $slug)->first()->id,
            'invoice_number' => 'INV-' . $so->so_number,
            'invoice_date'   => now()->toDateString(),
            'amount_due'     => $due,
            'amount_paid'    => $paid,
            'balance_due'    => $balance,
        ]);
    }

    private function makeReceipt(ArInvoice $inv, float $amount, string $slug = 'paid'): Receipt
    {
        return Receipt::create([
            'ar_invoice_id'  => $inv->id,
            'customer_id'    => $this->customer->id,
            'status_id'      => ListStatus::where('slug', $slug)->first()->id,
            'receipt_number' => 'REC-' . uniqid(),
            'receipt_type'   => 'payment',
            'receipt_date'   => now()->toDateString(),
            'amount_paid'    => $amount,
            'payment_mode'   => 'cash',
        ]);
    }

    private function queueReturn(SalesOrderItem $item, Receipt $receipt, int $qty, string $condition = 'restockable'): void
    {
        \DB::table('sales_return_items')->insert([
            'sales_order_item_id' => $item->id,
            'source_receipt_id'   => $receipt->id,
            'return_quantity'     => $qty,
            'return_condition'    => $condition,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Full cash return — restockable, no replacement
    // ──────────────────────────────────────────────────────────────────────────

    public function test_full_cash_return_records_all_tables(): void
    {
        $stock   = $this->makeStock($this->product, 'DR-001', 400.00, 10);
        $so      = $this->makeSo('cash', 2000.00);
        $item    = $this->addItem($so, $stock, 2, 1000.00);
        $invoice = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');

        $stockBefore = $stock->fresh()->quantity; // 10

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        // SO status
        $this->assertEquals('sales-returned', $so->fresh()->status->slug);

        // sales_order_items.returned_quantity incremented
        $this->assertEquals(2, $item->fresh()->returned_quantity);

        // sales_return_history — 1 row with correct values
        $this->assertDatabaseCount('sales_return_history', 1);
        $history = SalesReturnHistory::first();
        $this->assertEquals($item->id, $history->sales_order_item_id);
        $this->assertEquals($this->product->id, $history->product_id);
        $this->assertEquals(2, $history->quantity);
        $this->assertEquals('restockable', $history->condition);
        $this->assertEquals(1000.00, (float) $history->unit_price);
        $this->assertEquals(2000.00, (float) $history->total_value);
        $this->assertEquals($this->user->id, $history->approved_by_id);

        // inventory_adjustments — restockable returns ADD stock (type=1)
        $adj = InventoryAdjustment::where('inventory_stocks_id', $stock->id)
            ->orderByDesc('id')->first();
        $this->assertNotNull($adj);
        $this->assertEquals(1, $adj->type);
        $this->assertEquals($stockBefore + 2, (int) $adj->new_quantity);
        $this->assertEquals($stockBefore, (int) $adj->previous_quantity);

        // journal_entries — sales_return_revenue and sales_return_inventory
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_revenue']);
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_inventory']);
        $this->assertDatabaseMissing('journal_entries', ['entry_type' => 'sales_return_damage_writeoff']);
        $this->assertDatabaseMissing('journal_entries', ['entry_type' => 'replacement_inventory_out']);

        // no replacement records
        $this->assertDatabaseCount('sales_return_replacements', 0);

        // cash path: invoice amounts unchanged (no credit adjustment)
        $invoice->refresh();
        $this->assertEquals(2000.00, (float) $invoice->amount_due);
        $this->assertEquals(2000.00, (float) $invoice->amount_paid);

        // source receipt still has the audit note
        $receipt->refresh();
        $this->assertNotNull($receipt->notes);
        $this->assertStringContainsString('Return adjustment', $receipt->notes);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Full cash return — damaged item
    // ──────────────────────────────────────────────────────────────────────────

    public function test_full_cash_return_damaged_records_damage_adjustment_and_writeoff_je(): void
    {
        $stock   = $this->makeStock($this->product, 'DR-002', 400.00, 10);
        $so      = $this->makeSo('cash', 1000.00);
        $item    = $this->addItem($so, $stock, 1, 1000.00);
        $invoice = $this->makeInvoice($so, 1000, 1000, 0);
        $receipt = $this->makeReceipt($invoice, 1000);

        $this->queueReturn($item, $receipt, 1, 'damaged');

        $stockBefore = $stock->fresh()->quantity;

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        // sales_return_history — condition = damaged
        $history = SalesReturnHistory::first();
        $this->assertNotNull($history);
        $this->assertEquals('damaged', $history->condition);

        // inventory_adjustments — damaged uses recordLossOrDamage which stores 'damage' type
        $adj = InventoryAdjustment::where('inventory_stocks_id', $stock->id)
            ->orderByDesc('id')->first();
        $this->assertNotNull($adj);
        $this->assertEquals('damage', $adj->type);
        $this->assertEquals($stockBefore - 1, (int) $adj->new_quantity);

        // journal_entries — damage writeoff, NOT sales_return_inventory
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_revenue']);
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_damage_writeoff']);
        $this->assertDatabaseMissing('journal_entries', ['entry_type' => 'sales_return_inventory']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Full cash return — replacement equal to return value (no extra receipt)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_full_cash_return_with_equal_replacement_records_replacement_tables(): void
    {
        $stock    = $this->makeStock($this->product,  'DR-003', 400.00, 10);
        $repStock = $this->makeStock($this->productB, 'DR-004', 500.00, 10);
        $so       = $this->makeSo('cash', 2000.00);
        $item     = $this->addItem($so, $stock, 2, 1000.00);   // return value = ₱2,000
        $invoice  = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt  = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$item->id], [[
            'product_id' => $this->productB->id,
            'quantity'   => 2,
            'price'      => 1000.00,  // exactly ₱2,000 = return value
        ]]);

        // sales_return_replacements — 1 row
        $this->assertDatabaseCount('sales_return_replacements', 1);
        $rep = SalesReturnReplacement::first();
        $this->assertEquals($this->productB->id, $rep->product_id);
        $this->assertEquals(2, $rep->quantity);
        $this->assertEquals(1000.00, (float) $rep->price);
        $this->assertEquals(2000.00, (float) $rep->total_value);
        $this->assertEquals($this->user->id, $rep->replaced_by_id);

        // inventory_adjustments — 2 rows: +2 for return (restockable), -2 for replacement
        $addAdj = InventoryAdjustment::where('inventory_stocks_id', $stock->id)->first();
        $this->assertNotNull($addAdj);
        $this->assertEquals(1, $addAdj->type); // addition

        $deductAdj = InventoryAdjustment::where('inventory_stocks_id', $repStock->id)->first();
        $this->assertNotNull($deductAdj);
        $this->assertEquals(2, $deductAdj->type); // subtraction (deductStock type=2)

        // journal_entries — replacement_inventory_out but NO extra receipt JE
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'replacement_inventory_out']);

        // No extra receipt created (equal value)
        $extraReceipts = Receipt::where('receipt_type', 'payment')
            ->where('ar_invoice_id', $invoice->id)
            ->where('id', '!=', $receipt->id)
            ->get();
        $this->assertCount(0, $extraReceipts, 'No extra receipt should be created when replacement equals return value');

        // Source receipt notes contain replacement info
        $receipt->refresh();
        $this->assertStringContainsString('Replaced with:', $receipt->notes);
        $this->assertStringNotContainsString('Extra charged:', $receipt->notes);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Full cash return — replacement exceeds return value (extra receipt)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_full_cash_return_with_excess_replacement_creates_extra_receipt_and_je(): void
    {
        $stock    = $this->makeStock($this->product,  'DR-005', 400.00, 10);
        $repStock = $this->makeStock($this->productB, 'DR-006', 500.00, 10);
        $so       = $this->makeSo('cash', 2000.00);
        $item     = $this->addItem($so, $stock, 2, 1000.00);   // return value = ₱2,000
        $invoice  = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt  = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$item->id], [[
            'product_id' => $this->productB->id,
            'quantity'   => 3,
            'price'      => 1000.00,  // ₱3,000 replacement > ₱2,000 return → extra ₱1,000
        ]]);

        // Extra receipt created with the ₱1,000 difference
        $extraReceipts = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('id', '!=', $receipt->id)
            ->get();
        $this->assertCount(1, $extraReceipts);
        $extra = $extraReceipts->first();
        $this->assertEquals(1000.00, (float) $extra->amount_paid);

        // Invoice updated with extra amount
        $invoice->refresh();
        $this->assertEquals(3000.00, (float) $invoice->amount_due);

        // journal_entries — receipt_collection entry for extra receipt
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'replacement_inventory_out']);
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'receipt_collection']);

        // Source receipt notes contain extra charge reference
        $receipt->refresh();
        $this->assertStringContainsString('Extra charged', $receipt->notes);
        $this->assertStringContainsString($extra->receipt_number, $receipt->notes);

        // Extra receipt notes contain cross-reference back to source receipt
        $extra->refresh();
        $this->assertNotNull($extra->notes);
        $this->assertStringContainsString($receipt->receipt_number, $extra->notes);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Full credit return — no replacement
    // ──────────────────────────────────────────────────────────────────────────

    public function test_full_credit_return_cancels_invoice_and_receipt_and_creates_refund(): void
    {
        $stock   = $this->makeStock($this->product, 'DR-007', 400.00, 10);
        $so      = $this->makeSo('credit', 2000.00);
        $item    = $this->addItem($so, $stock, 2, 1000.00);
        $invoice = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        // SO status
        $this->assertEquals('sales-returned', $so->fresh()->status->slug);

        // sales_return_history recorded
        $this->assertDatabaseCount('sales_return_history', 1);

        // Invoice cancelled, amounts zeroed
        $invoice->refresh();
        $this->assertEquals('cancelled', $invoice->status->slug);
        $this->assertEquals(0.00, (float) $invoice->amount_due);
        $this->assertEquals(0.00, (float) $invoice->amount_paid);
        $this->assertEquals(0.00, (float) $invoice->balance_due);

        // Source receipt cancelled
        $receipt->refresh();
        $this->assertEquals('cancelled', $receipt->status->slug);

        // Refund receipt created
        $refundReceipts = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('receipt_type', 'refund')
            ->get();
        $this->assertCount(1, $refundReceipts);
        $refund = $refundReceipts->first();
        $this->assertEquals(2000.00, (float) $refund->amount_paid);

        // journal_entries — sales_return_revenue + sales_return_inventory
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_revenue']);
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_inventory']);

        // Source receipt notes recorded
        $receipt->refresh();
        $this->assertNotNull($receipt->notes);
        $this->assertStringContainsString('Return adjustment', $receipt->notes);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. Full credit return with replacement exceeding return value
    // ──────────────────────────────────────────────────────────────────────────

    public function test_full_credit_return_with_excess_replacement_resets_invoice_to_extra_only(): void
    {
        $stock    = $this->makeStock($this->product,  'DR-008', 400.00, 10);
        $repStock = $this->makeStock($this->productB, 'DR-009', 500.00, 10);
        $so       = $this->makeSo('credit', 2000.00);
        $item     = $this->addItem($so, $stock, 2, 1000.00);   // return value = ₱2,000
        $invoice  = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt  = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$item->id], [[
            'product_id' => $this->productB->id,
            'quantity'   => 1,
            'price'      => 2500.00,  // ₱2,500 > ₱2,000 return → extra ₱500
        ]]);

        // Invoice reset to 0, then extra charge added: amount_due = ₱500
        $invoice->refresh();
        $this->assertEquals(500.00, (float) $invoice->amount_due);
        $this->assertEquals(500.00, (float) $invoice->balance_due);

        // sales_return_replacements — 1 row
        $this->assertDatabaseCount('sales_return_replacements', 1);
        $rep = SalesReturnReplacement::first();
        $this->assertEquals($this->productB->id, $rep->product_id);
        $this->assertEquals(2500.00, (float) $rep->price);

        // Extra receipt with correct amount
        $extraReceipts = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('id', '!=', $receipt->id)
            ->whereNotIn('receipt_type', ['refund'])
            ->get();
        $this->assertCount(1, $extraReceipts);
        $this->assertEquals(500.00, (float) $extraReceipts->first()->amount_paid);

        // Refund receipt also created (credit path)
        $this->assertDatabaseHas('receipts', ['receipt_type' => 'refund']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. Partial cash return — only some items returned
    // ──────────────────────────────────────────────────────────────────────────

    public function test_partial_cash_return_only_increments_returned_items(): void
    {
        $stock  = $this->makeStock($this->product, 'DR-010', 400.00, 10);
        $so     = $this->makeSo('cash', 4000.00);
        $itemA  = $this->addItem($so, $stock, 2, 1000.00);  // returned
        $itemB  = $this->addItem($so, $stock, 2, 1000.00);  // NOT returned
        $invoice = $this->makeInvoice($so, 4000, 4000, 0);
        $receipt = $this->makeReceipt($invoice, 4000);

        // Only queue itemA for return
        $this->queueReturn($itemA, $receipt, 2, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id]);

        // SO status is partially-returned
        $this->assertEquals('partially-returned', $so->fresh()->status->slug);

        // returned_quantity: itemA incremented, itemB unchanged
        $this->assertEquals(2, $itemA->fresh()->returned_quantity);
        $this->assertEquals(0, $itemB->fresh()->returned_quantity);

        // sales_return_history — only for itemA
        $this->assertDatabaseCount('sales_return_history', 1);
        $history = SalesReturnHistory::first();
        $this->assertEquals($itemA->id, $history->sales_order_item_id);
        $this->assertEquals(2, $history->quantity);

        // inventory_adjustments — only 1 stock restoration (for itemA product only)
        $addedAdj = InventoryAdjustment::where('type', 1)->get();
        $this->assertCount(1, $addedAdj);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. Partial credit return — source cancelled, refund + updated receipts created
    // ──────────────────────────────────────────────────────────────────────────

    public function test_partial_credit_return_records_invoice_and_receipt_correctly(): void
    {
        $stock   = $this->makeStack($this->product, 'DR-011', 400.00, 10);
        $so      = $this->makeSo('credit', 4000.00);
        $itemA   = $this->addItem($so, $stock, 2, 1000.00);  // returned (₱2,000)
        $itemB   = $this->addItem($so, $stock, 2, 1000.00);  // kept
        $invoice = $this->makeInvoice($so, 4000, 4000, 0);
        $receipt = $this->makeReceipt($invoice, 4000);

        $this->queueReturn($itemA, $receipt, 2, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id]);

        // SO status partially-returned
        $this->assertEquals('partially-returned', $so->fresh()->status->slug);

        // Invoice amount_due and amount_paid reduced by ₱2,000 refund
        $invoice->refresh();
        $this->assertEquals(2000.00, (float) $invoice->amount_due);
        $this->assertEquals(2000.00, (float) $invoice->amount_paid);
        $this->assertEquals(0.00, (float) $invoice->balance_due);

        // Source receipt cancelled
        $receipt->refresh();
        $this->assertEquals('cancelled', $receipt->status->slug);

        // Refund receipt created (₱2,000)
        $refundReceipts = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('receipt_type', 'refund')
            ->get();
        $this->assertCount(1, $refundReceipts);
        $this->assertEquals(2000.00, (float) $refundReceipts->first()->amount_paid);

        // Updated receipt created (₱2,000 — remaining balance)
        $updatedReceipts = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('receipt_type', 'updated')
            ->get();
        $this->assertCount(1, $updatedReceipts);
        $this->assertEquals(2000.00, (float) $updatedReceipts->first()->amount_paid);

        // Source receipt notes
        $receipt->refresh();
        $this->assertStringContainsString('Return adjustment', $receipt->notes);

        // journal_entries created for return
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_revenue']);
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_inventory']);
        // journal entry for the updated receipt
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'receipt_collection']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. Partial credit return with replacement (> refund) — extra receipt + updated receipt
    // ──────────────────────────────────────────────────────────────────────────

    public function test_partial_credit_return_with_excess_replacement_creates_both_receipts(): void
    {
        $stock    = $this->makeStock($this->product,  'DR-012', 400.00, 10);
        $repStock = $this->makeStock($this->productB, 'DR-013', 500.00, 10);
        $so       = $this->makeSo('credit', 4000.00);
        $itemA    = $this->addItem($so, $stock, 2, 1000.00);  // returned (₱2,000)
        $itemB    = $this->addItem($so, $stock, 2, 1000.00);  // kept
        $invoice  = $this->makeInvoice($so, 4000, 4000, 0);
        $receipt  = $this->makeReceipt($invoice, 4000);

        $this->queueReturn($itemA, $receipt, 2, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id], [[
            'product_id' => $this->productB->id,
            'quantity'   => 3,
            'price'      => 1000.00,  // ₱3,000 replacement > ₱2,000 return → extra ₱1,000
        ]]);

        // sales_return_replacements — 1 row
        $this->assertDatabaseCount('sales_return_replacements', 1);

        // Refund receipt created
        $this->assertDatabaseHas('receipts', ['receipt_type' => 'refund']);

        // Updated receipt created
        $this->assertDatabaseHas('receipts', ['receipt_type' => 'updated']);

        // Extra receipt for the ₱1,000 difference
        $nonRefundReceipts = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('id', '!=', $receipt->id)
            ->whereNotIn('receipt_type', ['refund', 'updated'])
            ->get();
        $this->assertCount(1, $nonRefundReceipts);
        $this->assertEquals(1000.00, (float) $nonRefundReceipts->first()->amount_paid);

        // invoice: original ₱4,000 - ₱2,000 refund + ₱1,000 replacement extra = ₱3,000 due
        $invoice->refresh();
        $this->assertEquals(3000.00, (float) $invoice->amount_due);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. sales_return_items cleaned up after approval
    // ──────────────────────────────────────────────────────────────────────────

    public function test_sales_return_items_are_deleted_after_approval(): void
    {
        $stock   = $this->makeStock($this->product, 'DR-014', 400.00, 10);
        $so      = $this->makeSo('cash', 2000.00);
        $item    = $this->addItem($so, $stock, 2, 1000.00);
        $invoice = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');

        $this->assertDatabaseCount('sales_return_items', 1);

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        $this->assertDatabaseCount('sales_return_items', 0);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11. approved_by_id set on SO after return approval
    // ──────────────────────────────────────────────────────────────────────────

    public function test_approved_by_id_is_set_on_so_after_return_approval(): void
    {
        $stock   = $this->makeStock($this->product, 'DR-015', 400.00, 10);
        $so      = $this->makeSo('cash', 1000.00);
        $item    = $this->addItem($so, $stock, 1, 1000.00);
        $invoice = $this->makeInvoice($so, 1000, 1000, 0);
        $receipt = $this->makeReceipt($invoice, 1000);

        $this->queueReturn($item, $receipt, 1, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        $so->refresh();
        $this->assertEquals($this->user->id, $so->approved_by_id);
        $this->assertNotNull($so->approved_at);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 12. journal_entry_lines balance for each scenario
    // ──────────────────────────────────────────────────────────────────────────

    public function test_all_journal_entries_are_balanced_after_full_cash_return_with_replacement(): void
    {
        $stock    = $this->makeStock($this->product,  'DR-016', 400.00, 10);
        $repStock = $this->makeStock($this->productB, 'DR-017', 500.00, 10);
        $so       = $this->makeSo('cash', 2000.00);
        $item     = $this->addItem($so, $stock, 2, 1000.00);
        $invoice  = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt  = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$item->id], [[
            'product_id' => $this->productB->id,
            'quantity'   => 3,
            'price'      => 1000.00,  // ₱3,000 replacement → extra ₱1,000
        ]]);

        $entries = JournalEntry::with('lines')->get();
        foreach ($entries as $entry) {
            $debit  = $entry->lines->where('line_type', 'debit')->sum('amount');
            $credit = $entry->lines->where('line_type', 'credit')->sum('amount');
            $this->assertEquals(
                round($debit, 2),
                round($credit, 2),
                "Journal entry #{$entry->id} (type: {$entry->entry_type}) is unbalanced: debit={$debit}, credit={$credit}"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 13. Mixed conditions — restockable + damaged in same return
    // ──────────────────────────────────────────────────────────────────────────

    public function test_mixed_return_conditions_record_separate_adjustments_and_entries(): void
    {
        $stock  = $this->makeStock($this->product, 'DR-018', 400.00, 10);
        $so     = $this->makeSo('cash', 4000.00);
        $itemA  = $this->addItem($so, $stock, 2, 1000.00);  // restockable
        $itemB  = $this->addItem($so, $stock, 2, 1000.00);  // damaged
        $invoice = $this->makeInvoice($so, 4000, 4000, 0);
        $receipt = $this->makeReceipt($invoice, 4000);

        $this->queueReturn($itemA, $receipt, 2, 'restockable');
        $this->queueReturn($itemB, $receipt, 2, 'damaged');

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id, $itemB->id]);

        // 2 history rows — one per item
        $this->assertDatabaseCount('sales_return_history', 2);
        $conditions = SalesReturnHistory::pluck('condition')->toArray();
        $this->assertContains('restockable', $conditions);
        $this->assertContains('damaged', $conditions);

        // inventory_adjustments — type=1 (addition) for restockable, type='damage' for damaged
        $addAdj    = InventoryAdjustment::where('type', 1)->first();
        $damageAdj = InventoryAdjustment::where('type', 'damage')->first();
        $this->assertNotNull($addAdj,    'Restockable return must create type=1 inventory adjustment');
        $this->assertNotNull($damageAdj, 'Damaged return must create type=damage inventory adjustment');

        // journal_entries — both inventory and damage writeoff
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_revenue']);
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_inventory']);
        $this->assertDatabaseHas('journal_entries', ['entry_type' => 'sales_return_damage_writeoff']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 14. Partial return quantity — only returned qty incremented (not full qty)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_partial_quantity_return_increments_only_requested_quantity(): void
    {
        $stock   = $this->makeStock($this->product, 'DR-019', 400.00, 10);
        $so      = $this->makeSo('cash', 5000.00);
        $item    = $this->addItem($so, $stock, 5, 1000.00);  // 5 units ordered
        $invoice = $this->makeInvoice($so, 5000, 5000, 0);
        $receipt = $this->makeReceipt($invoice, 5000);

        // Only return 3 of the 5 units
        $this->queueReturn($item, $receipt, 3, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        // Partially returned — only 3 units
        $this->assertEquals(3, $item->fresh()->returned_quantity);

        // History records 3 units returned
        $history = SalesReturnHistory::first();
        $this->assertNotNull($history);
        $this->assertEquals(3, $history->quantity);
        $this->assertEquals(3000.00, (float) $history->total_value); // 3 × ₱1,000

        // Inventory adjustment adds 3 units back
        $adj = InventoryAdjustment::where('type', 1)->first();
        $this->assertNotNull($adj);
        $this->assertEquals(3, $adj->new_quantity - $adj->previous_quantity);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 15. No replacement — sales_return_replacements stays empty
    // ──────────────────────────────────────────────────────────────────────────

    public function test_return_without_replacement_leaves_replacement_table_empty(): void
    {
        $stock   = $this->makeStock($this->product, 'DR-020', 400.00, 10);
        $so      = $this->makeSo('cash', 2000.00);
        $item    = $this->addItem($so, $stock, 2, 1000.00);
        $invoice = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        $this->assertDatabaseCount('sales_return_replacements', 0);
        $this->assertDatabaseMissing('journal_entries', ['entry_type' => 'replacement_inventory_out']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helper (typo fix — makeStack → makeStock alias)
    // ──────────────────────────────────────────────────────────────────────────

    private function makeStack(Product $product, string $batch, float $cost, int $qty): InventoryStocks
    {
        return $this->makeStock($product, $batch, $cost, $qty);
    }
}
