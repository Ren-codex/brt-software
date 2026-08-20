<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\InventoryStocks;
use App\Models\ListBrand;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\ProductConversion;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Modules\SalesOrderClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Settling a cash sale at the counter with more than one method at once —
 * part cash, part bank transfer, part check — in a single transaction.
 *
 * Each line becomes its own official receipt carrying its own bank account and
 * reference, so the collection can be reconciled against the bank statement and
 * so remittance approval can move each portion to the account it really landed in.
 */
class SalesSplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Customer $customer;
    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'pending' => 'Pending', 'paid' => 'Paid', 'unpaid' => 'Unpaid',
            'closed' => 'Closed', 'for-payment' => 'For Payment',
            'partially-paid' => 'Partially Paid',
        ] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $brand = ListBrand::create(['name' => 'Test Brand', 'is_active' => 1]);
        $unit = ListUnit::create(['name' => 'Sack', 'is_active' => 1]);

        $this->product = Product::create([
            'code' => 'JR-25', 'brand_id' => $brand->id, 'unit_id' => $unit->id,
            'weight' => 25, 'is_active' => 1,
        ]);

        // Stock is only visible to the sales flow when it traces back to a
        // received item or a conversion; a conversion is the cheaper of the two
        // to stand up here, and has to point at the stock it produced.
        $stock = InventoryStocks::create([
            'batch_code' => 'BATCH-001', 'product_id' => $this->product->id,
            'quantity' => 100, 'retail_price' => 1500, 'wholesale_price' => 1400, 'unit_cost' => 1200,
        ]);

        $conversion = ProductConversion::create([
            'source_stock_id' => $stock->id, 'output_stock_id' => $stock->id,
            'source_qty_used' => 0, 'conversion_ratio' => 1, 'output_quantity' => 100,
            'reason' => 'Test fixture', 'converted_by_id' => $this->user->id,
            'conversion_date' => now()->toDateString(),
        ]);

        $stock->update(['conversion_id' => $conversion->id]);

        $this->customer = Customer::create([
            'name' => 'ABC Trading', 'address' => 'Zamboanga City',
            'contact_number' => '09170000000', 'is_active' => 1,
            'added_by_id' => $this->user->id,
        ]);

        $this->bank = BankAccount::create([
            'bank_name' => 'BDO', 'account_name' => 'BRT Operating', 'account_number' => '00112233',
            'gl_code' => '1020', 'balance' => 500000, 'is_active' => 1,
        ]);
    }

    /** Two sacks at 1,500 = 3,000 due. */
    private function saveOrder(array $overrides = []): array
    {
        $request = new Request();
        $request->merge(array_merge([
            'order_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'payment_mode' => 'Cash',
            'delivery_location' => 'Zamboanga City',
            'items' => [[
                'product_id' => $this->product->id, 'quantity' => 2, 'price' => 1500,
                'price_type' => 'retail', 'batch_code' => 'BATCH-001', 'discount_per_unit' => 0,
            ]],
        ], $overrides));

        return app(SalesOrderClass::class)->save($request);
    }

    public function test_a_split_cash_sale_creates_one_receipt_per_payment_method(): void
    {
        $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 1000],
                ['payment_mode' => 'Bank Transfer', 'payment_amount' => 1500, 'bank_account_id' => $this->bank->id, 'reference_number' => 'TRN-8891'],
                ['payment_mode' => 'Check', 'payment_amount' => 500, 'reference_number' => '000123'],
            ],
        ]);

        $receipts = Receipt::orderBy('id')->get();

        $this->assertCount(3, $receipts);
        $this->assertEqualsCanonicalizing(
            ['Cash', 'Bank Transfer', 'Check'],
            $receipts->pluck('payment_mode')->all()
        );
        $this->assertEquals(3000.0, round($receipts->sum('amount_paid'), 2));

        // Each receipt gets its own OR number.
        $this->assertCount(3, $receipts->pluck('receipt_number')->unique());
    }

    public function test_the_bank_transfer_line_keeps_its_bank_account_and_reference(): void
    {
        $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 2000],
                ['payment_mode' => 'Bank Transfer', 'payment_amount' => 1000, 'bank_account_id' => $this->bank->id, 'reference_number' => 'TRN-8891'],
            ],
        ]);

        $transfer = Receipt::where('payment_mode', 'Bank Transfer')->firstOrFail();

        $this->assertEquals($this->bank->id, $transfer->bank_account_id);
        $this->assertEquals('TRN-8891', $transfer->reference_number);

        // Cash needs neither, and must not inherit them from the transfer.
        $cash = Receipt::where('payment_mode', 'Cash')->firstOrFail();
        $this->assertNull($cash->bank_account_id);
        $this->assertNull($cash->reference_number);
    }

    public function test_a_check_line_keeps_its_check_number(): void
    {
        $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 2500],
                ['payment_mode' => 'Check', 'payment_amount' => 500, 'reference_number' => '000123'],
            ],
        ]);

        $this->assertEquals('000123', Receipt::where('payment_mode', 'Check')->firstOrFail()->reference_number);
    }

    public function test_an_order_paid_several_ways_is_recorded_as_split(): void
    {
        $result = $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 2000],
                ['payment_mode' => 'Check', 'payment_amount' => 1000, 'reference_number' => '000123'],
            ],
        ]);

        $order = SalesOrder::findOrFail($result['data']->id);

        $this->assertEquals('Split', $order->payment_mode);
        $this->assertCount(2, $order->payment_lines);
    }

    public function test_a_single_line_keeps_its_own_payment_mode_rather_than_split(): void
    {
        $result = $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Bank Transfer', 'payment_amount' => 3000, 'bank_account_id' => $this->bank->id, 'reference_number' => 'TRN-1'],
            ],
        ]);

        $this->assertEquals('Bank Transfer', SalesOrder::findOrFail($result['data']->id)->payment_mode);
    }

    public function test_payment_lines_must_settle_the_order_in_full(): void
    {
        $this->expectException(ValidationException::class);

        $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 1000],
            ],
        ]);
    }

    public function test_overpaying_across_the_lines_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 2000],
                ['payment_mode' => 'Check', 'payment_amount' => 2000, 'reference_number' => '1'],
            ],
        ]);
    }

    public function test_a_bank_transfer_without_a_reference_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 2000],
                ['payment_mode' => 'Bank Transfer', 'payment_amount' => 1000, 'bank_account_id' => $this->bank->id],
            ],
        ]);
    }

    public function test_a_check_without_a_number_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 2000],
                ['payment_mode' => 'Check', 'payment_amount' => 1000],
            ],
        ]);
    }

    public function test_an_order_sent_without_payment_lines_still_creates_one_receipt(): void
    {
        $this->saveOrder();

        $receipts = Receipt::get();

        $this->assertCount(1, $receipts);
        $this->assertEquals('Cash', $receipts->first()->payment_mode);
        $this->assertEquals(3000.0, round((float) $receipts->first()->amount_paid, 2));
    }

    public function test_a_split_sale_leaves_the_invoice_fully_settled(): void
    {
        $result = $this->saveOrder([
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 1500],
                ['payment_mode' => 'Check', 'payment_amount' => 1500, 'reference_number' => '000123'],
            ],
        ]);

        $invoice = ArInvoice::where('sales_order_id', $result['data']->id)->firstOrFail();

        $this->assertEquals(0.0, round((float) $invoice->balance_due, 2));
        $this->assertEquals(3000.0, round((float) $invoice->amount_paid, 2));
        $this->assertEquals('closed', SalesOrder::findOrFail($result['data']->id)->status->slug);
    }

    public function test_a_credit_sale_ignores_payment_lines_entirely(): void
    {
        $result = $this->saveOrder([
            'payment_mode' => 'Credit Sales',
            'due_date' => now()->addDays(30)->toDateString(),
            'payment_lines' => [
                ['payment_mode' => 'Cash', 'payment_amount' => 3000],
            ],
        ]);

        $order = SalesOrder::findOrFail($result['data']->id);

        $this->assertEquals('Credit Sales', $order->payment_mode);
        $this->assertNull($order->payment_lines);
        $this->assertCount(0, Receipt::get());
    }
}
