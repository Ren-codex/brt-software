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
 * A credit sale asks for a supervisor's approval because it commits the
 * business to collecting later. Due today defers nothing, so there is nothing
 * to approve and the sale goes straight through.
 *
 * These post over HTTP on purpose: the rule lives in the controller, past the
 * FormRequest, and a service-level test would not reach it.
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

    private function supervisorToken(): string
    {
        $admin = User::factory()->create([
            'username' => 'sup' . uniqid(),
            'password' => Hash::make('secret-password'),
        ]);
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], ['type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $admin->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $admin->id]);

        return app(SupervisorAuthorization::class)
            ->issue($admin->username, 'secret-password', 'sales.credit_sale', '127.0.0.1');
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

    public function test_a_credit_sale_due_today_needs_no_authorization(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_a_credit_sale_due_later_is_refused_without_authorization(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload(['due_date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('supervisor_token');

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_a_credit_sale_due_later_goes_through_once_authorized(): void
    {
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload([
                'due_date' => now()->addDays(30)->toDateString(),
                'supervisor_token' => $this->supervisorToken(),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_a_due_date_already_past_defers_nothing_either(): void
    {
        // Money already collectible is not a fresh commitment, and backdating
        // only makes the invoice overdue sooner -- it is no way around approval.
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload(['due_date' => now()->subDay()->toDateString()]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_a_credit_sale_with_no_due_date_still_needs_authorization(): void
    {
        // Open-ended credit is the most deferred kind there is. Reading a
        // missing date as "due today" would turn the exemption into a bypass.
        $this->actingAs($this->user)
            ->post('/sales-orders', $this->payload(['payment_mode' => 'Credit Sales', 'due_date' => null]))
            ->assertSessionHasErrors('supervisor_token');

        $this->assertSame(0, SalesOrder::count());
    }
}
