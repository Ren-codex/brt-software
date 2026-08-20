<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryStocks;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListUnit;
use App\Models\Module;
use App\Models\Product;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Modules\InventoryReportClass;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The inventory position report: what is held now and what it is worth.
 *
 * Deliberately not date-filtered — stock on hand is a balance, not a flow.
 * Filtering it by when the stock row was created is what made the dashboard
 * report ₱0 on any day nothing came in.
 */
class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    private InventoryReportClass $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
        $this->reports = app(InventoryReportClass::class);
    }

    private function product(string $brandName, int $minimum = 0): Product
    {
        return Product::create([
            'code' => 'P' . uniqid(),
            'brand_id' => ListBrand::create(['name' => $brandName, 'is_active' => 1])->id,
            'unit_id' => ListUnit::create(['name' => 'Sack', 'is_active' => 1])->id,
            'weight' => 25,
            'minimum_stock' => $minimum,
            'is_active' => 1,
        ]);
    }

    private function stock(Product $product, array $attributes = []): InventoryStocks
    {
        return InventoryStocks::create(array_merge([
            'batch_code' => 'B' . uniqid(),
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 100,
            'retail_price' => 150,
            'wholesale_price' => 130,
        ], $attributes));
    }

    public function test_stock_value_uses_cost_and_retail_value_is_reported_separately(): void
    {
        $this->stock($this->product('Jasmine'), ['quantity' => 10, 'unit_cost' => 100, 'retail_price' => 150]);

        $totals = $this->reports->summary([])['totals'];

        $this->assertEquals(1000.0, $totals['stock_value'], 'Stock value is unit cost × quantity.');
        $this->assertEquals(1500.0, $totals['retail_value'], 'Retail value is kept apart from cost.');
    }

    public function test_a_batch_at_or_below_its_minimum_is_low_stock(): void
    {
        $this->stock($this->product('Basmati', 10), ['quantity' => 10]);

        $report = $this->reports->summary([]);

        $this->assertEquals('low_stock', $report['rows'][0]['status']);
        $this->assertEquals(1, $report['totals']['low_stock']);
    }

    public function test_an_empty_batch_is_out_of_stock_and_hidden_by_default(): void
    {
        $this->stock($this->product('Brown'), ['quantity' => 0]);

        $this->assertCount(0, $this->reports->summary([])['rows'], 'Empty batches are hidden unless asked for.');

        $withEmpty = $this->reports->summary(['include_empty' => true]);
        $this->assertCount(1, $withEmpty['rows']);
        $this->assertEquals('out_of_stock', $withEmpty['rows'][0]['status']);
    }

    public function test_a_batch_expiring_within_thirty_days_is_flagged(): void
    {
        $this->stock($this->product('Dinorado'), ['expiration_date' => now()->addDays(10)->toDateString()]);

        $this->assertEquals('expiring', $this->reports->summary([])['rows'][0]['status']);
    }

    public function test_a_batch_past_its_expiry_is_flagged_as_expired(): void
    {
        $this->stock($this->product('Sinandomeng'), ['expiration_date' => now()->subDay()->toDateString()]);

        $this->assertEquals('expired', $this->reports->summary([])['rows'][0]['status']);
    }

    public function test_running_out_matters_more_than_expiring(): void
    {
        $this->stock($this->product('Malagkit', 20), [
            'quantity' => 5, 'expiration_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->assertEquals('low_stock', $this->reports->summary([])['rows'][0]['status']);
    }

    public function test_archived_batches_are_left_out(): void
    {
        $this->stock($this->product('Archived'), ['is_archived' => 1]);

        $this->assertCount(0, $this->reports->summary([])['rows']);
    }

    public function test_the_brand_filter_narrows_the_rows(): void
    {
        $this->stock($this->product('Jasmine'));
        $this->stock($this->product('Basmati'));

        $report = $this->reports->summary(['brand' => 'Jasmine']);

        $this->assertCount(1, $report['rows']);
        $this->assertEquals('Jasmine', $report['rows'][0]['brand']);
    }

    public function test_the_keyword_matches_a_batch_code(): void
    {
        $this->stock($this->product('Jasmine'), ['batch_code' => 'FINDME-001']);
        $this->stock($this->product('Basmati'));

        $report = $this->reports->summary(['keyword' => 'findme']);

        $this->assertCount(1, $report['rows']);
        $this->assertEquals('FINDME-001', $report['rows'][0]['batch_code']);
    }

    public function test_low_stock_only_hides_healthy_batches(): void
    {
        $this->stock($this->product('Healthy', 1), ['quantity' => 500]);
        $this->stock($this->product('Short', 100), ['quantity' => 5]);

        $report = $this->reports->summary(['low_stock_only' => true]);

        $this->assertCount(1, $report['rows']);
        $this->assertEquals('Short', $report['rows'][0]['brand']);
    }

    public function test_totals_are_grouped_by_brand(): void
    {
        $jasmine = $this->product('Jasmine');
        $this->stock($jasmine, ['quantity' => 10, 'unit_cost' => 100]);
        $this->stock($jasmine, ['quantity' => 5, 'unit_cost' => 100]);
        $this->stock($this->product('Basmati'), ['quantity' => 1, 'unit_cost' => 50]);

        $byBrand = collect($this->reports->summary([])['by_brand'])->keyBy('brand');

        $this->assertEquals(1500.0, $byBrand['Jasmine']['stock_value']);
        $this->assertEquals(2, $byBrand['Jasmine']['batches']);
        $this->assertEquals(50.0, $byBrand['Basmati']['stock_value']);
    }

    public function test_the_report_is_not_limited_to_recently_created_stock(): void
    {
        // The dashboard's old mistake: stock created outside the window vanished.
        $stock = $this->stock($this->product('Old'), ['quantity' => 8, 'unit_cost' => 25]);
        $stock->forceFill(['created_at' => now()->subYear()])->save();

        $this->assertEquals(200.0, $this->reports->summary([])['totals']['stock_value']);
    }

    public function test_received_stock_takes_its_cost_from_the_received_item(): void
    {
        // Received batches store unit_cost on received_items and leave the stock
        // row at 0; reading only the row valued them at nothing.
        //
        // Note this test alone would not have caught the original fault: MySQL
        // returns the decimal column as the string '0.0000', which is truthy, so
        // a ?: fallback never fired. SQLite returns a falsy 0 and the test passed
        // regardless. The service now compares numerically; verified against the
        // MySQL database directly, where the total went 35,420 -> 22,975,620.
        $product = $this->product('Received');
        $stock = $this->stock($product, ['quantity' => 100, 'unit_cost' => 0]);

        // A received item hangs off a receipt, which hangs off a purchase order.
        $user = User::factory()->create();
        $supplier = \App\Models\ListSupplier::create([
            'name' => 'S' . uniqid(), 'address' => 'Zamboanga', 'contact_person' => 'P',
            'contact_number' => '09170000000', 'email' => uniqid() . '@test.com',
            'tin' => '000', 'is_active' => 1, 'is_blacklisted' => 0,
        ]);
        $status = \App\Models\ListStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
        $po = \App\Models\PurchaseOrder::create([
            'po_number' => 'PO-' . uniqid(), 'po_date' => now()->toDateString(),
            'total_amount' => 230000, 'supplier_id' => $supplier->id,
            'created_by_id' => $user->id, 'status_id' => $status->id,
        ]);
        $receipt = \App\Models\ReceivedStock::create([
            'received_no' => 'RS-' . uniqid(),
            'received_date' => now(),
            'payment_mode' => 'Credit',
            'amount_paid' => 0,
            'received_by_id' => $user->id,
            'po_id' => $po->id,
            'supplier_id' => $supplier->id,
        ]);

        $poItem = \App\Models\PurchaseOrderItem::create([
            'po_id' => $po->id, 'product_id' => $product->id,
            'quantity' => 100, 'unit_cost' => 2300, 'total_cost' => 230000,
        ]);

        $received = \App\Models\ReceivedItem::create([
            'received_id' => $receipt->id,
            'po_item_id' => $poItem->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_cost' => 2300,
            'total_cost' => 230000,
        ]);
        $stock->forceFill(['received_item_id' => $received->id])->save();

        $totals = $this->reports->summary([])['totals'];

        $this->assertEquals(230000.0, $totals['stock_value'], 'Cost should fall back to the received item.');
    }

    public function test_a_converted_batch_keeps_the_cost_on_its_own_row(): void
    {
        $this->stock($this->product('Converted'), ['quantity' => 10, 'unit_cost' => 460]);

        $this->assertEquals(4600.0, $this->reports->summary([])['totals']['stock_value']);
    }

    public function test_current_covers_every_batch_regardless_of_age(): void
    {
        $old = $this->stock($this->product('Old'), ['quantity' => 4, 'unit_cost' => 100]);
        $old->forceFill(['created_at' => now()->subYears(2)])->save();
        $this->stock($this->product('New'), ['quantity' => 1, 'unit_cost' => 100]);

        $this->assertCount(2, $this->reports->summary(['period' => 'current'])['rows']);
    }

    public function test_a_period_narrows_to_batches_that_arrived_in_it(): void
    {
        $old = $this->stock($this->product('LastYear'), ['quantity' => 4, 'unit_cost' => 100]);
        $old->forceFill(['created_at' => now()->subYear()])->save();
        $this->stock($this->product('ThisMonth'), ['quantity' => 1, 'unit_cost' => 100]);

        $rows = $this->reports->summary(['period' => 'month'])['rows'];

        $this->assertCount(1, $rows);
        $this->assertEquals('ThisMonth', $rows[0]['brand']);
    }

    public function test_each_supported_period_is_accepted(): void
    {
        $this->stock($this->product('Any'));

        foreach (\App\Services\Modules\InventoryReportClass::PERIODS as $period) {
            $report = $this->reports->summary(['period' => $period]);
            $this->assertIsArray($report['rows'], "Period {$period} should return rows.");
        }
    }

    public function test_an_unknown_period_falls_back_to_the_full_position(): void
    {
        $old = $this->stock($this->product('Old'), ['quantity' => 4, 'unit_cost' => 100]);
        $old->forceFill(['created_at' => now()->subYears(3)])->save();

        $user = $this->userWith('inventory', 'inventory_stocks', 'view');
        $body = $this->actingAs($user)->getJson('/inventory-report?option=summary&period=nonsense')->assertOk()->json();

        $this->assertEquals('current', $body['filters']['period']);
        $this->assertCount(1, $body['rows'], 'A bad period must not silently hide stock.');
    }

    private function userWith(string $module, ?string $submodule, string $level): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $mod = Module::where('key', $module)->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $mod->id,
            'submodule_id' => $submodule ? $mod->submodules()->where('key', $submodule)->firstOrFail()->id : null,
            'access_level' => $level,
        ]);

        return $user;
    }

    public function test_a_warehouse_manager_may_open_the_report(): void
    {
        $user = $this->userWith('inventory', 'inventory_stocks', 'view');

        $this->actingAs($user)->getJson('/inventory-report?option=summary')->assertOk();
    }

    public function test_someone_without_inventory_access_is_refused(): void
    {
        $user = $this->userWith('payroll', 'payroll_processing', 'view');

        $this->actingAs($user)->getJson('/inventory-report?option=summary')->assertForbidden();
    }
}
