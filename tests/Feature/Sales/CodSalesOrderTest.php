<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\InventoryStocks;
use App\Models\JournalEntryLine;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductConversion;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cash on delivery: the goods go out before the money comes in, so the sale is
 * receivable until the driver returns with the cash — not a cash sale, which
 * would book collected money and close the order before anyone has collected
 * anything.
 *
 * It is still not credit: nothing is deferred beyond the handover, so it needs
 * no supervisor and no credit limit, and it is due on the delivery date.
 */
class CodSalesOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $statuses = [
            'pending' => 'Pending', 'unpaid' => 'Unpaid', 'for-payment' => 'For Payment',
            'paid' => 'Paid', 'partially-paid' => 'Partially Paid', 'closed' => 'Closed',
            'approved' => 'Approved', 'cancelled' => 'Cancelled',
        ];

        foreach ($statuses as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }

        $this->user = $this->encoder();

        $brand = ListBrand::create(['name' => 'Test Brand', 'is_active' => 1]);
        $unit = ListUnit::create(['name' => 'Sack', 'is_active' => 1]);

        $this->product = Product::create([
            'code' => 'JR-25', 'brand_id' => $brand->id, 'unit_id' => $unit->id,
            'weight' => 25, 'is_active' => 1,
        ]);

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
    }

    private function encoder(): User
    {
        $role = ListRole::create(['name' => 'Encoder '.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'sales_orders')->firstOrFail()->id,
            'access_level' => 'encoder',
        ]);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'order_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'payment_mode' => 'COD',
            'delivery_location' => 'Zamboanga City',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'items' => [[
                'product_id' => $this->product->id, 'quantity' => 2, 'price' => 1500,
                'price_type' => 'retail', 'batch_code' => 'BATCH-001', 'discount_per_unit' => 0,
            ]],
        ], $overrides);
    }

    private function postCod(array $overrides = [])
    {
        return $this->actingAs($this->user)->post('/sales-orders', $this->payload($overrides));
    }

    public function test_a_cod_order_needs_no_supervisor_even_when_delivery_is_days_away(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_a_cod_order_is_owed_until_it_is_collected(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $invoice = ArInvoice::firstOrFail();
        $this->assertSame(3000.0, (float) $invoice->balance_due);
        $this->assertSame(0.0, (float) $invoice->amount_paid);
        $this->assertSame('unpaid', $invoice->status->slug);
        $this->assertFalse($invoice->receipts()->exists(), 'No receipt should exist before anyone collects.');
    }

    public function test_a_cod_order_stays_open_rather_than_closing_on_save(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->assertSame('for-payment', SalesOrder::firstOrFail()->status->slug);
    }

    public function test_a_cod_sale_is_booked_as_receivable_not_as_collected_cash(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $debitedCodes = JournalEntryLine::where('line_type', 'debit')
            ->with('account')
            ->get()
            ->pluck('account.code')
            ->all();

        $this->assertContains('1100', $debitedCodes, 'COD should debit Accounts Receivable.');
        $this->assertNotContains('1050', $debitedCodes, 'COD must not book Undeposited Collections before collection.');
    }

    public function test_the_due_date_follows_the_delivery_date(): void
    {
        $delivery = now()->addDays(3)->toDateString();

        // Even if the browser posts something else, delivery day is when COD is due.
        $this->postCod(['delivery_date' => $delivery, 'due_date' => now()->addDays(30)->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame($delivery, SalesOrder::firstOrFail()->due_date->toDateString());
    }

    public function test_cod_requires_a_delivery_date(): void
    {
        $this->postCod(['delivery_date' => null])->assertSessionHasErrors('delivery_date');

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_a_credit_limit_does_not_block_a_cod_order(): void
    {
        // Nothing is extended: the customer pays the driver at the door.
        $this->customer->update(['credit_limit' => 1]);

        $this->postCod()->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }
}
