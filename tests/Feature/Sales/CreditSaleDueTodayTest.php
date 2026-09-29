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
use App\Services\System\Permission\SupervisorAuthorization;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A credit sale used to need a supervisor's credentials whenever it deferred
 * payment. That approval is gone: a credit sale saves like any other, and the
 * customer's credit limit is what refuses one now.
 *
 * These post over HTTP on purpose: the rule lived in the controller, past the
 * FormRequest, and a service-level test would not have reached it.
 */
class CreditSaleDueTodayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['pending' => 'Pending', 'unpaid' => 'Unpaid', 'for-payment' => 'For Payment'] as $slug => $name) {
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
        $role = ListRole::create(['name' => 'Encoder ' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
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
            'payment_mode' => 'Credit',
            'delivery_location' => 'Zamboanga City',
            'due_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $this->product->id, 'quantity' => 2, 'price' => 1500,
                'price_type' => 'retail', 'batch_code' => 'BATCH-001', 'discount_per_unit' => 0,
            ]],
        ], $overrides);
    }

    public function test_a_credit_sale_saves_without_any_approval(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_a_credit_sale_due_much_later_needs_no_approval_either(): void
    {
        // A supervisor used to have to sign anything deferred. The credit limit
        // is the control now.
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload(['due_date' => now()->addDays(30)->toDateString()]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_open_ended_credit_saves_too(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload(['payment_mode' => 'Credit Sales', 'due_date' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_the_credit_limit_still_refuses_an_order_beyond_it(): void
    {
        // What stops a credit sale now is the customer's own limit.
        $this->customer->update(['credit_limit' => 1000]);

        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload(['due_date' => now()->addDays(30)->toDateString()]))
            ->assertSessionHasErrors('credit_limit');

        $this->assertSame(0, SalesOrder::count());
    }
}
