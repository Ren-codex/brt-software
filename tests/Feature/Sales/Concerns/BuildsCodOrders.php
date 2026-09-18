<?php

namespace Tests\Feature\Sales\Concerns;

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
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;

/**
 * One COD order's worth of fixture: an encoder who may save sales orders, a
 * product with 100 sacks in BATCH-001, and a customer to deliver to.
 *
 * A cash sale runs straight through to a paid invoice and a closed order, so
 * the whole chain of statuses has to exist even when a test never pays.
 */
trait BuildsCodOrders
{
    private User $user;

    private Product $product;

    private Customer $customer;

    private function seedCodFixture(): void
    {
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
}
