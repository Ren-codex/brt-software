<?php

namespace Tests\Feature\SalesReturn;

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
use App\Models\SalesReturnHistory;
use App\Models\SalesReturnReplacement;
use App\Models\User;
use App\Services\Libraries\ReceiptClass;
use App\Services\Modules\SalesOrderClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComplexReturnScenariosTest extends TestCase
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
            'name' => 'Customer', 'address' => 'Addr', 'contact_number' => '09000000001',
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
        $stId  = ListStatus::where('slug', 'paid')->first()->id;
        $poId  = \DB::table('purchase_orders')->insertGetId([
            'supplier_id' => $supId, 'status_id' => $stId, 'created_by_id' => $this->user->id,
            'po_date' => now()->toDateString(), 'total_amount' => ($qty + 10) * $cost,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $piId  = \DB::table('purchase_order_items')->insertGetId([
            'po_id' => $poId, 'product_id' => $product->id,
            'quantity' => $qty + 10, 'unit_cost' => $cost, 'total_cost' => ($qty + 10) * $cost,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $rsId  = \DB::table('received_stocks')->insertGetId([
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

    private function makeSo(string $mode, float $total, string $statusSlug = 'sales-return-approval'): SalesOrder
    {
        return SalesOrder::create([
            'so_number'    => 'SO-CX-' . uniqid(),
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', $statusSlug)->first()->id,
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

    private function assertJournalEntriesBalanced(): void
    {
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
    // 1. Journal entries always balance
    // ──────────────────────────────────────────────────────────────────────────

    public function test_journal_entries_balanced_after_full_credit_return(): void
    {
        $stock   = $this->makeStock($this->product, 'JB-001', 400.00, 10);
        $so      = $this->makeSo('credit', 2000.00);
        $item    = $this->addItem($so, $stock, 2, 1000.00);
        $invoice = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');
        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        $this->assertJournalEntriesBalanced();
    }

    public function test_journal_entries_balanced_after_partial_credit_return(): void
    {
        $stock   = $this->makeStock($this->product, 'JB-002', 400.00, 10);
        $so      = $this->makeSo('credit', 4000.00);
        $itemA   = $this->addItem($so, $stock, 2, 1000.00);
        $itemB   = $this->addItem($so, $stock, 2, 1000.00);
        $invoice = $this->makeInvoice($so, 4000, 4000, 0);
        $receipt = $this->makeReceipt($invoice, 4000);

        $this->queueReturn($itemA, $receipt, 2, 'restockable');
        app(SalesOrderClass::class)->approve($so->id, [$itemA->id]);

        $this->assertJournalEntriesBalanced();
    }

    public function test_journal_entries_balanced_after_cash_return_with_replacement(): void
    {
        $stock    = $this->makeStock($this->product,  'JB-003', 400.00, 10);
        $repStock = $this->makeStock($this->productB, 'JB-004', 500.00, 10);
        $so       = $this->makeSo('cash', 2000.00);
        $item     = $this->addItem($so, $stock, 2, 1000.00);
        $invoice  = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt  = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');
        app(SalesOrderClass::class)->approve($so->id, [$item->id], [[
            'product_id' => $this->productB->id,
            'quantity'   => 2,
            'price'      => 1000.00,
        ]]);

        $this->assertJournalEntriesBalanced();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Mixed conditions in same return
    // ──────────────────────────────────────────────────────────────────────────

    public function test_mixed_restockable_and_damaged_creates_both_journal_entries(): void
    {
        $stock  = $this->makeStock($this->product, 'MX-001', 400.00, 10);
        $so     = $this->makeSo('credit', 4000.00);
        $itemA  = $this->addItem($so, $stock, 2, 1000.00);
        $itemB  = $this->addItem($so, $stock, 2, 1000.00);
        $inv    = $this->makeInvoice($so, 4000, 4000, 0);
        $rcpt   = $this->makeReceipt($inv, 4000);

        \DB::table('sales_return_items')->insert([
            ['sales_order_item_id' => $itemA->id, 'source_receipt_id' => $rcpt->id,
             'return_quantity' => 2, 'return_condition' => 'restockable', 'created_at' => now(), 'updated_at' => now()],
            ['sales_order_item_id' => $itemB->id, 'source_receipt_id' => $rcpt->id,
             'return_quantity' => 2, 'return_condition' => 'damaged', 'created_at' => now(), 'updated_at' => now()],
        ]);

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id, $itemB->id]);

        // Inventory restoration JE for restockable
        $this->assertNotNull(
            JournalEntry::where('entry_type', 'sales_return_inventory')->first(),
            'Restockable item must create an inventory restoration JE'
        );

        // Damage write-off JE for damaged
        $this->assertNotNull(
            JournalEntry::where('entry_type', 'sales_return_damage_writeoff')->first(),
            'Damaged item must create a damage write-off JE'
        );

        // Same product stock: restockable addStock(+2) then damaged recordLossOrDamage(-2) → net 0 change.
        $this->assertEquals(10, $stock->fresh()->quantity,
            'Net stock: 10 + 2 restockable - 2 damaged write-off = 10.');

        $this->assertJournalEntriesBalanced();
    }

    public function test_mixed_conditions_inventory_adjustments_are_correct(): void
    {
        $stock  = $this->makeStock($this->product, 'MX-002', 400.00, 10);
        $so     = $this->makeSo('credit', 4000.00);
        $itemA  = $this->addItem($so, $stock, 2, 1000.00); // will be restockable
        $itemB  = $this->addItem($so, $stock, 3, 1000.00); // will be damaged
        $inv    = $this->makeInvoice($so, 5000, 5000, 0);
        $rcpt   = $this->makeReceipt($inv, 5000);

        \DB::table('sales_return_items')->insert([
            ['sales_order_item_id' => $itemA->id, 'source_receipt_id' => $rcpt->id,
             'return_quantity' => 2, 'return_condition' => 'restockable', 'created_at' => now(), 'updated_at' => now()],
            ['sales_order_item_id' => $itemB->id, 'source_receipt_id' => $rcpt->id,
             'return_quantity' => 3, 'return_condition' => 'damaged', 'created_at' => now(), 'updated_at' => now()],
        ]);

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id, $itemB->id]);

        // Started at 10. Restockable adds 2 → 12. Damaged records write-off (deducts from stock) → 9.
        $this->assertEquals(9, $stock->fresh()->quantity,
            'Stock: 10 + 2 restockable - 3 damaged write-off = 9');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Double-return prevention
    // ──────────────────────────────────────────────────────────────────────────

    public function test_approve_throws_when_so_already_sales_returned(): void
    {
        $stock = $this->makeStock($this->product, 'DR-001', 400.00, 10);
        $so    = $this->makeSo('cash', 1000.00, 'sales-returned');
        $item  = $this->addItem($so, $stock, 1, 1000.00);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already been returned');

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);
    }

    public function test_approve_throws_when_so_partially_returned(): void
    {
        $stock = $this->makeStock($this->product, 'DR-002', 400.00, 10);
        $so    = $this->makeSo('cash', 2000.00, 'partially-returned');
        $item  = $this->addItem($so, $stock, 2, 1000.00, 1);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already been returned');

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Return history rows
    // ──────────────────────────────────────────────────────────────────────────

    public function test_return_history_row_created_for_each_returned_item(): void
    {
        $stock  = $this->makeStock($this->product, 'RH-001', 400.00, 10);
        $so     = $this->makeSo('credit', 4000.00);
        $itemA  = $this->addItem($so, $stock, 2, 1000.00);
        $itemB  = $this->addItem($so, $stock, 2, 1000.00);
        $inv    = $this->makeInvoice($so, 4000, 4000, 0);
        $rcpt   = $this->makeReceipt($inv, 4000);

        \DB::table('sales_return_items')->insert([
            ['sales_order_item_id' => $itemA->id, 'source_receipt_id' => $rcpt->id,
             'return_quantity' => 2, 'return_condition' => 'restockable', 'created_at' => now(), 'updated_at' => now()],
            ['sales_order_item_id' => $itemB->id, 'source_receipt_id' => $rcpt->id,
             'return_quantity' => 1, 'return_condition' => 'damaged', 'created_at' => now(), 'updated_at' => now()],
        ]);

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id, $itemB->id]);

        $history = SalesReturnHistory::where('sales_order_id', $so->id)->get();
        $this->assertCount(2, $history, 'One history row per returned item');

        $histA = $history->firstWhere('sales_order_item_id', $itemA->id);
        $histB = $history->firstWhere('sales_order_item_id', $itemB->id);

        $this->assertEquals(2, $histA->quantity,  'Item A history quantity must be 2');
        $this->assertEquals(1, $histB->quantity,  'Item B history quantity must be 1');
        $this->assertEquals('restockable', $histA->condition);
        $this->assertEquals('damaged',     $histB->condition);
        $this->assertEquals(2000.00, (float) $histA->total_value);
        $this->assertEquals(1000.00, (float) $histB->total_value);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Credit full return + replacement > return value (invoice state)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_full_credit_return_with_extra_replacement_invoice_amounts_are_correct(): void
    {
        // Return ₱2,000 worth. Replace with ₱2,500 → extra ₱500.
        $returnStock = $this->makeStock($this->product,  'FR-001', 400.00, 10);
        $repStock    = $this->makeStock($this->productB, 'FR-002', 500.00, 10);

        $so      = $this->makeSo('credit', 2000.00);
        $item    = $this->addItem($so, $returnStock, 2, 1000.00);
        $invoice = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');
        app(SalesOrderClass::class)->approve($so->id, [$item->id], [[
            'product_id' => $this->productB->id,
            'quantity'   => 2,
            'price'      => 1250.00, // ₱2,500 total → ₱500 extra
        ]]);

        $invoice->refresh();

        // The extra ₱500 must be the only charge remaining on the invoice.
        // After full return: original ₱2,000 is fully returned.
        // The extra receipt for ₱500 is the new obligation.
        $this->assertEquals('500.00', $invoice->amount_due,
            'After full return, amount_due must reflect only the ₱500 extra replacement charge');
        $this->assertEquals('0.00', $invoice->amount_paid,
            'No payment has been made toward the extra charge yet');
        $this->assertEquals('500.00', $invoice->balance_due,
            'Customer still owes ₱500 for the extra replacement value');

        // Extra receipt exists for ₱500
        $extra = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('receipt_type', 'payment')
            ->where('amount_paid', 500.00)
            ->first();
        $this->assertNotNull($extra, 'An extra receipt for ₱500 must exist');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. Cash full return without replacement — invoice untouched
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cash_full_return_without_replacement_leaves_invoice_unchanged(): void
    {
        $stock   = $this->makeStock($this->product, 'CF-001', 400.00, 10);
        $so      = $this->makeSo('cash', 2000.00);
        $item    = $this->addItem($so, $stock, 2, 1000.00);
        $invoice = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt = $this->makeReceipt($invoice, 2000);

        $this->queueReturn($item, $receipt, 2, 'restockable');
        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        $invoice->refresh();
        // Cash with no replacement: stock is restored, JEs are recorded,
        // but the invoice itself stays at original values (no cash refund flow).
        $this->assertEquals('2000.00', $invoice->amount_due,
            'Cash return without replacement: invoice amount_due unchanged');
        $this->assertEquals('2000.00', $invoice->amount_paid,
            'Cash return without replacement: invoice amount_paid unchanged');
        $this->assertEquals('0.00', $invoice->balance_due,
            'Cash return without replacement: balance_due still zero');

        $this->assertEquals('sales-returned', $so->fresh()->status->slug);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. End-to-end: full return + extra receipt payment → SO stays returned
    // ──────────────────────────────────────────────────────────────────────────

    public function test_paying_extra_receipt_after_full_return_keeps_so_as_returned(): void
    {
        $returnStock = $this->makeStock($this->product,  'E2E-001', 400.00, 10);
        $repStock    = $this->makeStock($this->productB, 'E2E-002', 500.00, 10);

        $so      = $this->makeSo('credit', 2000.00);
        $item    = $this->addItem($so, $returnStock, 2, 1000.00);
        $invoice = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt = $this->makeReceipt($invoice, 2000);

        // Approve return with extra replacement (₱500 extra)
        $this->queueReturn($item, $receipt, 2, 'restockable');
        app(SalesOrderClass::class)->approve($so->id, [$item->id], [[
            'product_id' => $this->productB->id,
            'quantity'   => 2,
            'price'      => 1250.00,
        ]]);

        $so->refresh();
        $this->assertEquals('sales-returned', $so->status->slug,
            'SO must be sales-returned after full return approval');

        // Now customer pays the extra ₱500 via ReceiptClass::save()
        $invoice->refresh();
        $payRequest = new Request();
        $payRequest->merge([
            'ar_invoice_id' => $invoice->id,
            'receipt_date'  => now()->toDateString(),
            'amount_paid'   => $invoice->balance_due,
            'payment_mode'  => 'cash',
        ]);

        app(ReceiptClass::class)->save($payRequest);

        // Critical: SO must remain sales-returned, not become partially-paid or closed
        $this->assertEquals('sales-returned', $so->fresh()->status->slug,
            'Paying the extra receipt must not change SO status from sales-returned');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. adjustment() eligibility guards
    // ──────────────────────────────────────────────────────────────────────────

    public function test_adjustment_rejected_for_cancelled_so(): void
    {
        $stock = $this->makeStock($this->product, 'AG-001', 400.00, 10);
        $so    = $this->makeSo('cash', 1000.00, 'cancelled');
        $item  = $this->addItem($so, $stock, 1, 1000.00);
        $inv   = $this->makeInvoice($so, 1000, 1000, 0);
        $rcpt  = $this->makeReceipt($inv, 1000);

        $request = new Request();
        $request->merge([
            'id'                => $so->id,
            'type'              => 'Sales Return',
            'reason'            => 'test',
            'receipt_id'        => $rcpt->id,
            'item_ids'          => [$item->id],
            'return_quantities' => [$item->id => 1],
            'return_conditions' => [$item->id => 'restockable'],
        ]);

        $this->expectException(ValidationException::class);
        app(SalesOrderClass::class)->adjustment($request);
    }

    public function test_adjustment_rejected_for_already_returned_so(): void
    {
        $stock = $this->makeStock($this->product, 'AG-002', 400.00, 10);
        $so    = $this->makeSo('cash', 1000.00, 'sales-returned');
        $item  = $this->addItem($so, $stock, 1, 1000.00);
        $inv   = $this->makeInvoice($so, 1000, 1000, 0);
        $rcpt  = $this->makeReceipt($inv, 1000);

        $request = new Request();
        $request->merge([
            'id'                => $so->id,
            'type'              => 'Sales Return',
            'reason'            => 'test',
            'receipt_id'        => $rcpt->id,
            'item_ids'          => [$item->id],
            'return_quantities' => [$item->id => 1],
            'return_conditions' => [$item->id => 'restockable'],
        ]);

        $this->expectException(ValidationException::class);
        app(SalesOrderClass::class)->adjustment($request);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. Revenue reversal JE amount matches refund
    // ──────────────────────────────────────────────────────────────────────────

    public function test_revenue_reversal_amount_equals_refund_amount(): void
    {
        // 3 units @ ₱800 discount ₱50/unit → effective price ₱750/unit
        // Return 2 units → refund = 2 × 750 = 1500
        $stock   = $this->makeStock($this->product, 'RR-001', 400.00, 10);
        $so      = SalesOrder::create([
            'so_number' => 'SO-RR-001', 'order_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'status_id' => ListStatus::where('slug', 'sales-return-approval')->first()->id,
            'total_amount' => 2250, 'payment_mode' => 'credit', 'added_by_id' => $this->user->id,
        ]);

        $item = new SalesOrderItem([
            'product_id' => $this->product->id, 'quantity' => 3, 'price' => 800.00,
            'price_type' => 'retail', 'batch_code' => $stock->batch_code, 'discount_per_unit' => 50.00,
        ]);
        $item->sales_order_id = $so->id;
        $item->save();

        $invoice = $this->makeInvoice($so, 2250, 2250, 0);
        $receipt = $this->makeReceipt($invoice, 2250);

        $this->queueReturn($item, $receipt, 2, 'restockable');
        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        $revenueJe = JournalEntry::where('entry_type', 'sales_return_revenue')->first();
        $this->assertNotNull($revenueJe, 'A sales_return_revenue JE must exist');

        $lines = JournalEntryLine::where('journal_entry_id', $revenueJe->id)->get();
        $this->assertCount(2, $lines);
        $this->assertEquals('1500.00', $lines->first()->amount, 'Revenue reversal must equal 2 × (800 - 50) = 1,500');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. Replacement items in a CREDIT return — accounting + DB both work
    // ──────────────────────────────────────────────────────────────────────────

    public function test_credit_partial_return_with_replacement_works_end_to_end(): void
    {
        $returnStock = $this->makeStock($this->product,  'CR-001', 400.00, 10);
        $repStock    = $this->makeStock($this->productB, 'CR-002', 500.00, 10);

        // 3-item SO, return 1 item and replace with replacement product (same value)
        $so      = $this->makeSo('credit', 3000.00);
        $itemA   = $this->addItem($so, $returnStock, 1, 1000.00); // returned
        $itemB   = $this->addItem($so, $returnStock, 2, 1000.00); // kept
        $invoice = $this->makeInvoice($so, 3000, 3000, 0);
        $receipt = $this->makeReceipt($invoice, 3000);

        $this->queueReturn($itemA, $receipt, 1, 'restockable');
        app(SalesOrderClass::class)->approve($so->id, [$itemA->id], [[
            'product_id' => $this->productB->id,
            'quantity'   => 1,
            'price'      => 1000.00, // equal value → no extra
        ]]);

        // SO is partially-returned (itemB kept)
        $this->assertEquals('partially-returned', $so->fresh()->status->slug);

        // Replacement persisted
        $this->assertCount(1, SalesReturnReplacement::where('sales_order_id', $so->id)->get());

        // Replacement stock deducted
        $this->assertEquals(9, $repStock->fresh()->quantity, 'Replacement stock: 10 - 1 = 9');

        // Return stock restored
        $this->assertEquals(11, $returnStock->fresh()->quantity, 'Returned stock: 10 + 1 = 11');

        // Replacement JE created
        $this->assertNotNull(
            JournalEntry::where('entry_type', 'replacement_inventory_out')->first(),
            'replacement_inventory_out JE must exist'
        );

        // Invoice adjusted correctly (partial credit return): ₱3,000 → ₱2,000
        $invoice->refresh();
        $this->assertEquals('2000.00', $invoice->amount_due,   'Invoice amount_due must be ₱2,000 (kept items only)');
        $this->assertEquals('2000.00', $invoice->amount_paid,  'Invoice amount_paid must be ₱2,000');
        $this->assertEquals('0.00',    $invoice->balance_due,  'Balance must be zero (already paid for kept items)');

        $this->assertJournalEntriesBalanced();
    }
}
