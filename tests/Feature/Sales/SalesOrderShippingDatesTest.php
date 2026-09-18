<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\InventoryStocks;
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
 * Shipping and delivery dates are planned when the order is encoded and can be
 * corrected later by editing it. Both are optional, so orders with no plan yet
 * still save.
 *
 * These post over HTTP because the value has to survive the whole path — form
 * body, FormRequest rules, validated(), then the service — and a service-level
 * test would not catch a rule that drops it on the way.
 */
class SalesOrderShippingDatesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        // A cash sale runs straight through to a paid invoice and a closed
        // order, so the whole chain of statuses has to exist.
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
            'payment_mode' => 'Cash',
            'delivery_location' => 'Zamboanga City',
            'items' => [[
                'product_id' => $this->product->id, 'quantity' => 2, 'price' => 1500,
                'price_type' => 'retail', 'batch_code' => 'BATCH-001', 'discount_per_unit' => 0,
            ]],
        ], $overrides);
    }

    public function test_both_dates_are_saved_with_a_new_order(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload([
                'shipping_date' => '2026-09-20',
                'delivery_date' => '2026-09-22',
            ]))
            ->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->assertSame('2026-09-20', $order->shipping_date->toDateString());
        $this->assertSame('2026-09-22', $order->delivery_date->toDateString());
    }

    public function test_an_order_saves_without_either_date(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload())
            ->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->assertNull($order->shipping_date);
        $this->assertNull($order->delivery_date);
    }

    public function test_editing_an_order_updates_both_dates(): void
    {
        // A credit sale, because a cash sale closes on the spot and a closed
        // order can no longer be edited.
        $credit = ['payment_mode' => 'Credit', 'due_date' => now()->toDateString()];

        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload($credit + [
                'shipping_date' => '2026-09-20',
                'delivery_date' => '2026-09-22',
            ]))
            ->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, $this->payload($credit + [
                'shipping_date' => '2026-09-25',
                'delivery_date' => '2026-09-26',
            ]))
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('2026-09-25', $order->shipping_date->toDateString());
        $this->assertSame('2026-09-26', $order->delivery_date->toDateString());
    }

    public function test_a_delivery_date_before_the_shipping_date_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload([
                'shipping_date' => '2026-09-22',
                'delivery_date' => '2026-09-20',
            ]))
            ->assertSessionHasErrors('delivery_date');

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_delivery_on_the_shipping_date_is_allowed(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload([
                'shipping_date' => '2026-09-22',
                'delivery_date' => '2026-09-22',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_a_delivery_date_alone_is_accepted(): void
    {
        // Nothing to compare against, so there is no ordering to break.
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload(['delivery_date' => '2026-09-20']))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-20', SalesOrder::firstOrFail()->delivery_date->toDateString());
    }
}
