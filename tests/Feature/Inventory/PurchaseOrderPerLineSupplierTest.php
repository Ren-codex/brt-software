<?php

namespace Tests\Feature\Inventory;

use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Module;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A purchase order can now carry products from several suppliers. The supplier
 * is stored on each line; the order keeps a representative supplier (the first
 * line's) so the printed Bill-To and the lists still have one to show.
 */
class PurchaseOrderPerLineSupplierTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        // save() consumes the purchase_request series, so it must exist.
        \DB::table('series')->insert([
            'name' => 'Purchase Request', 'slug' => 'purchase_request', 'prefix' => 'PR-',
            'current_date' => now()->toDateString(), 'starting_value' => 1, 'max_digit' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->user = $this->encoder();
    }

    private function encoder(): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'inventory')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'purchase_orders')->firstOrFail()->id,
            'access_level' => 'encoder',
        ]);

        return $user;
    }

    private function supplier(string $name): int
    {
        return \DB::table('list_suppliers')->insertGetId([
            'name' => $name, 'address' => 'Addr', 'contact_person' => 'P',
            'contact_number' => '0900', 'email' => uniqid().'@t.com', 'tin' => '000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function product(string $code): Product
    {
        return Product::create([
            'code' => $code, 'weight' => 25,
            'unit_id' => ListUnit::firstOrCreate(['name' => 'Sack'], ['is_active' => 1])->id,
            'brand_id' => ListBrand::firstOrCreate(['name' => $code.' Brand'], ['is_active' => 1])->id,
            'is_active' => 1, 'minimum_stock' => 1,
        ]);
    }

    public function test_each_line_keeps_its_own_supplier_and_a_mixed_order_is_represented_by_the_first(): void
    {
        $supplierA = $this->supplier('Supplier A');
        $supplierB = $this->supplier('Supplier B');
        $rice = $this->product('RICE');
        $corn = $this->product('CORN');

        $this->actingAs($this->user)->post('/purchase-orders', [
            'total_amount' => 3000,
            'items' => [
                ['product_id' => $rice->id, 'supplier_id' => $supplierA, 'quantity' => 10, 'unit_cost' => 100, 'total_cost' => 1000],
                ['product_id' => $corn->id, 'supplier_id' => $supplierB, 'quantity' => 20, 'unit_cost' => 100, 'total_cost' => 2000],
            ],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::latest('id')->firstOrFail();

        // Each line carries its own supplier.
        $this->assertSame($supplierA, PurchaseOrderItem::where('po_id', $po->id)->where('product_id', $rice->id)->value('supplier_id'));
        $this->assertSame($supplierB, PurchaseOrderItem::where('po_id', $po->id)->where('product_id', $corn->id)->value('supplier_id'));

        // The order's representative supplier is the first line's.
        $this->assertSame($supplierA, $po->supplier_id);
    }

    public function test_an_order_where_every_line_shares_a_supplier_takes_that_supplier(): void
    {
        $supplierA = $this->supplier('Supplier A');
        $rice = $this->product('RICE');
        $corn = $this->product('CORN');

        $this->actingAs($this->user)->post('/purchase-orders', [
            'total_amount' => 3000,
            'items' => [
                ['product_id' => $rice->id, 'supplier_id' => $supplierA, 'quantity' => 10, 'unit_cost' => 100, 'total_cost' => 1000],
                ['product_id' => $corn->id, 'supplier_id' => $supplierA, 'quantity' => 20, 'unit_cost' => 100, 'total_cost' => 2000],
            ],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::latest('id')->firstOrFail();
        $this->assertSame($supplierA, $po->supplier_id);
    }

    public function test_a_line_without_a_supplier_is_rejected(): void
    {
        $supplierA = $this->supplier('Supplier A');
        $rice = $this->product('RICE');

        $this->actingAs($this->user)->post('/purchase-orders', [
            'total_amount' => 1000,
            'items' => [
                ['product_id' => $rice->id, 'quantity' => 10, 'unit_cost' => 100, 'total_cost' => 1000],
            ],
        ])->assertSessionHasErrors('items.0.supplier_id');

        $this->assertSame(0, PurchaseOrder::count());
    }
}
