<?php

namespace Tests\Feature\SalesReturn;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\InventoryStocks;
use App\Models\ListStatus;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceivedItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\Modules\SalesOrderClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Tests for complex sales return scenarios introduced in the 2026-06-25 fixes.
 * Each test maps to a specific scenario number from the code review.
 */
class ComplexScenariosTest extends TestCase
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

        foreach ([
            'sales-return-approval', 'sales-returned', 'partially-returned',
            'paid', 'cancelled', 'closed', 'for-payment', 'unpaid', 'partially-paid',
            'pending', 'allowance-applied',
        ] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'text_color' => '#fff',
                'bg_color' => '#333',
            ]);
        }

        $unitId  = \DB::table('list_units')->insertGetId(['name' => 'Sack', 'created_at' => now(), 'updated_at' => now()]);
        $brandId = \DB::table('list_brands')->insertGetId(['name' => 'Test Brand', 'created_at' => now(), 'updated_at' => now()]);

        $this->product = Product::create([
            'code'      => 'TEST-' . uniqid(),
            'weight'    => 50,
            'unit_id'   => $unitId,
            'brand_id'  => $brandId,
            'is_active' => true,
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

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeStock(string $batchCode, float $unitCost, int $qty): InventoryStocks
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
            'product_id' => $this->product->id,
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
            'product_id'  => $this->product->id,
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

    private function makeSoItem(SalesOrder $so, InventoryStocks $stock, int $qty, float $price, int $returnedQty = 0): SalesOrderItem
    {
        $item = new SalesOrderItem([
            'product_id'        => $this->product->id,
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

    private function makeInvoice(SalesOrder $so, float $amountDue, float $amountPaid, float $balanceDue, string $statusSlug = 'paid'): ArInvoice
    {
        return ArInvoice::create([
            'sales_order_id' => $so->id,
            'status_id'      => ListStatus::where('slug', $statusSlug)->first()->id,
            'invoice_number' => 'INV-' . $so->so_number,
            'invoice_date'   => now()->toDateString(),
            'amount_due'     => $amountDue,
            'amount_paid'    => $amountPaid,
            'balance_due'    => $balanceDue,
        ]);
    }

    private function makeReceipt(ArInvoice $invoice, float $amountPaid, string $type = 'payment', string $statusSlug = 'paid'): Receipt
    {
        return Receipt::create([
            'ar_invoice_id'  => $invoice->id,
            'customer_id'    => $this->customer->id,
            'status_id'      => ListStatus::where('slug', $statusSlug)->first()->id,
            'receipt_number' => 'REC-' . uniqid(),
            'receipt_type'   => $type,
            'receipt_date'   => now()->toDateString(),
            'amount_paid'    => $amountPaid,
            'payment_mode'   => 'cash',
        ]);
    }

    // ─── Scenario 1: Partial return correctly reduces amount_due ─────────────

    public function test_partial_return_reduces_amount_due_on_invoice(): void
    {
        // ₱4,000 SO (2 items × ₱2,000), fully paid. Return Item A only (₱2,000).
        $stock = $this->makeStock('BATCH-S1-001', 400.00, 10);

        $so = SalesOrder::create([
            'so_number'    => 'SO-S1-001',
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'sales-return-approval')->first()->id,
            'total_amount' => 4000,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        $itemA = $this->makeSoItem($so, $stock, 2, 1000.00);
        $itemB = $this->makeSoItem($so, $stock, 2, 1000.00);

        $invoice = $this->makeInvoice($so, 4000, 4000, 0);
        $receipt = $this->makeReceipt($invoice, 4000);

        // Submit return for itemA only (qty=2, price=₱1,000 → refund ₱2,000)
        \DB::table('sales_return_items')->insert([
            'sales_order_item_id' => $itemA->id,
            'source_receipt_id'   => $receipt->id,
            'return_quantity'     => 2,
            'return_condition'    => 'restockable',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id]);

        $invoice->refresh();

        // amount_due: 4000 - 2000 = 2000 (value of kept items)
        $this->assertEquals('2000.00', $invoice->amount_due, 'amount_due must drop by the refund value');
        // amount_paid: 4000 - 2000 = 2000 (original payment minus the refund)
        $this->assertEquals('2000.00', $invoice->amount_paid, 'amount_paid must drop by the refund value');
        // balance_due: 2000 - 2000 = 0 (remaining item already fully covered)
        $this->assertEquals('0.00', $invoice->balance_due, 'balance_due must be zero');
        // SO status
        $this->assertEquals('partially-returned', $so->fresh()->status->slug, 'SO must become partially-returned');
    }

    public function test_partial_return_on_partially_paid_invoice_shows_correct_balance(): void
    {
        // ₱6,000 SO (3 items × ₱2,000), only ₱2,000 paid. Return 1 item (₱2,000).
        $stock = $this->makeStock('BATCH-S1-002', 400.00, 10);

        $so = SalesOrder::create([
            'so_number'    => 'SO-S1-002',
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'sales-return-approval')->first()->id,
            'total_amount' => 6000,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        $itemA = $this->makeSoItem($so, $stock, 2, 1000.00); // ₱2,000
        $itemB = $this->makeSoItem($so, $stock, 2, 1000.00); // ₱2,000
        $itemC = $this->makeSoItem($so, $stock, 2, 1000.00); // ₱2,000

        // Only ₱2,000 of ₱6,000 paid so far
        $invoice = $this->makeInvoice($so, 6000, 2000, 4000, 'partially-paid');
        $receipt = $this->makeReceipt($invoice, 2000);

        // Return itemA (₱2,000) — the exact amount that was paid
        \DB::table('sales_return_items')->insert([
            'sales_order_item_id' => $itemA->id,
            'source_receipt_id'   => $receipt->id,
            'return_quantity'     => 2,
            'return_condition'    => 'restockable',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id]);

        $invoice->refresh();

        // amount_due: 6000 - 2000 = 4000
        $this->assertEquals('4000.00', $invoice->amount_due, 'amount_due must drop to ₱4,000');
        // amount_paid: max(0, 2000 - 2000) = 0 (full refund of what was paid)
        $this->assertEquals('0.00', $invoice->amount_paid, 'amount_paid must drop to zero after full refund');
        // balance_due: 4000 - 0 = 4000
        $this->assertEquals('4000.00', $invoice->balance_due, 'customer still owes ₱4,000 for the 2 remaining items');
    }

    // ─── Scenario 3: returned_quantity prevents double returns ────────────────

    public function test_returned_quantity_increments_after_partial_return_approval(): void
    {
        $stock = $this->makeStock('BATCH-S3-001', 300.00, 10);

        $so = SalesOrder::create([
            'so_number'    => 'SO-S3-001',
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'sales-return-approval')->first()->id,
            'total_amount' => 3000,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        // itemA: qty=3, itemB: qty=2 — returning 2 units of itemA only
        $itemA = $this->makeSoItem($so, $stock, 3, 1000.00);
        $itemB = $this->makeSoItem($so, $stock, 2, 500.00);

        $this->makeInvoice($so, 4000, 4000, 0);

        \DB::table('sales_return_items')->insert([
            'sales_order_item_id' => $itemA->id,
            'source_receipt_id'   => null,
            'return_quantity'     => 2,
            'return_condition'    => 'restockable',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id]);

        $this->assertEquals(2, $itemA->fresh()->returned_quantity, 'returned_quantity must be 2 after approving 2 units');
        $this->assertEquals(0, $itemB->fresh()->returned_quantity, 'itemB returned_quantity must remain 0');
    }

    public function test_adjustment_blocks_return_when_item_already_fully_returned(): void
    {
        $stock = $this->makeStock('BATCH-S3-002', 300.00, 10);

        $so = SalesOrder::create([
            'so_number'    => 'SO-S3-002',
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'partially-returned')->first()->id,
            'total_amount' => 3000,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        // All 3 units already returned
        $item = $this->makeSoItem($so, $stock, 3, 1000.00, returnedQty: 3);

        $invoice = $this->makeInvoice($so, 3000, 3000, 0);
        $receipt = $this->makeReceipt($invoice, 0, 'updated');

        $request = new Request();
        $request->merge([
            'id'                => $so->id,
            'type'              => 'Sales Return',
            'reason'            => 'Second return attempt',
            'receipt_id'        => $receipt->id,
            'item_ids'          => [$item->id],
            'return_quantities' => [$item->id => 2],
            'return_conditions' => [$item->id => 'restockable'],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has already been fully returned');

        app(SalesOrderClass::class)->adjustment($request);
    }

    public function test_adjustment_blocks_return_quantity_exceeding_remaining(): void
    {
        $stock = $this->makeStock('BATCH-S3-003', 300.00, 10);

        $so = SalesOrder::create([
            'so_number'    => 'SO-S3-003',
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'partially-returned')->first()->id,
            'total_amount' => 3000,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        // qty=3, already returned 2 → only 1 remains
        $item = $this->makeSoItem($so, $stock, 3, 1000.00, returnedQty: 2);

        $invoice = $this->makeInvoice($so, 3000, 3000, 0);
        $receipt = $this->makeReceipt($invoice, 1000, 'updated');

        $request = new Request();
        $request->merge([
            'id'                => $so->id,
            'type'              => 'Sales Return',
            'reason'            => 'Excess qty attempt',
            'receipt_id'        => $receipt->id,
            'item_ids'          => [$item->id],
            'return_quantities' => [$item->id => 2], // tries to return 2 but only 1 left
            'return_conditions' => [$item->id => 'restockable'],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must be between 1 and 1');

        app(SalesOrderClass::class)->adjustment($request);
    }

    // ─── Scenario 5: Credit → Cash edit creates a receipt ────────────────────

    public function test_receipt_created_when_credit_so_converted_to_cash(): void
    {
        $stock = $this->makeStock('BATCH-S5-001', 300.00, 20);

        $so = SalesOrder::create([
            'so_number'    => 'SO-S5-001',
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'for-payment')->first()->id,
            'total_amount' => 3000,
            'payment_mode' => 'credit',
            'added_by_id'  => $this->user->id,
        ]);

        $this->makeSoItem($so, $stock, 3, 1000.00);

        $invoice = $this->makeInvoice($so, 3000, 0, 3000, 'unpaid');

        // Assert no receipt before the edit
        $this->assertCount(0, Receipt::where('ar_invoice_id', $invoice->id)->get(), 'Credit SO must start with no receipt');

        // Edit: change payment_mode to cash
        $request = new Request();
        $request->merge([
            'id'           => $so->id,
            'customer_id'  => $this->customer->id,
            'order_date'   => now()->toDateString(),
            'payment_mode' => 'cash',
            'sales_rep_id' => null,
            'driver_id'    => null,
            'location_id'  => null,
            'items'        => [[
                'product_id'        => $this->product->id,
                'quantity'          => 3,
                'price'             => 1000.00,
                'price_type'        => 'retail',
                'batch_code'        => $stock->batch_code,
                'discount_per_unit' => 0,
            ]],
        ]);

        app(SalesOrderClass::class)->update($request);

        $receipts = Receipt::where('ar_invoice_id', $invoice->id)->where('receipt_type', 'payment')->get();
        $this->assertCount(1, $receipts, 'A payment receipt must be created when converting Credit SO to Cash');
        $this->assertEquals('3000.00', $receipts->first()->amount_paid, 'Receipt must cover the full SO amount');
        $this->assertEquals('closed', $so->fresh()->status->slug, 'SO must be closed after Cash conversion');
    }

    // ─── Scenario 6: Full return does not create a ₱0 updated receipt ────────

    public function test_full_return_does_not_create_updated_receipt(): void
    {
        $stock = $this->makeStock('BATCH-S6-001', 500.00, 10);

        $so = SalesOrder::create([
            'so_number'    => 'SO-S6-001',
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'sales-return-approval')->first()->id,
            'total_amount' => 2000,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        $item = $this->makeSoItem($so, $stock, 2, 1000.00);

        $invoice = $this->makeInvoice($so, 2000, 2000, 0);
        $receipt = $this->makeReceipt($invoice, 2000);

        \DB::table('sales_return_items')->insert([
            'sales_order_item_id' => $item->id,
            'source_receipt_id'   => $receipt->id,
            'return_quantity'     => 2, // all 2 units — full return
            'return_condition'    => 'restockable',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        app(SalesOrderClass::class)->approve($so->id, [$item->id]);

        $updatedReceipts = Receipt::where('ar_invoice_id', $invoice->id)->where('receipt_type', 'updated')->get();
        $this->assertCount(0, $updatedReceipts, 'Full return must not create a confusing ₱0 updated receipt');

        $refundReceipts = Receipt::where('ar_invoice_id', $invoice->id)->where('receipt_type', 'refund')->get();
        $this->assertCount(1, $refundReceipts, 'Full return must create exactly one refund receipt');
        $this->assertEquals('2000.00', $refundReceipts->first()->amount_paid, 'Refund receipt must be for the full ₱2,000');

        $this->assertEquals('sales-returned', $so->fresh()->status->slug, 'SO must be marked sales-returned');
    }

    public function test_partial_return_still_creates_updated_receipt(): void
    {
        // Ensure the partial return path still creates the updated receipt (regression guard)
        $stock = $this->makeStock('BATCH-S6-002', 500.00, 10);

        $so = SalesOrder::create([
            'so_number'    => 'SO-S6-002',
            'order_date'   => now()->toDateString(),
            'customer_id'  => $this->customer->id,
            'status_id'    => ListStatus::where('slug', 'sales-return-approval')->first()->id,
            'total_amount' => 4000,
            'payment_mode' => 'cash',
            'added_by_id'  => $this->user->id,
        ]);

        $itemA = $this->makeSoItem($so, $stock, 2, 1000.00); // ₱2,000
        $itemB = $this->makeSoItem($so, $stock, 2, 1000.00); // ₱2,000 — stays

        $invoice = $this->makeInvoice($so, 4000, 4000, 0);
        $receipt = $this->makeReceipt($invoice, 4000);

        // Return itemA only — partial return
        \DB::table('sales_return_items')->insert([
            'sales_order_item_id' => $itemA->id,
            'source_receipt_id'   => $receipt->id,
            'return_quantity'     => 2,
            'return_condition'    => 'restockable',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        app(SalesOrderClass::class)->approve($so->id, [$itemA->id]);

        // Partial return SHOULD create an updated receipt (₱2,000 remaining payment)
        $updatedReceipts = Receipt::where('ar_invoice_id', $invoice->id)->where('receipt_type', 'updated')->get();
        $this->assertCount(1, $updatedReceipts, 'Partial return must still create an updated receipt');
        $this->assertEquals('2000.00', $updatedReceipts->first()->amount_paid, 'Updated receipt must reflect ₱2,000 remaining payment');

        $this->assertEquals('partially-returned', $so->fresh()->status->slug, 'SO must become partially-returned');
    }
}
