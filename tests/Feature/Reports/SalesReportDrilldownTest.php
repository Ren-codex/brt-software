<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Module;
use App\Models\Product;
use App\Models\RolePermission;
use App\Models\SalesOrder;

use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Drill-down behind a Sales Report row, and the rep scoping the reports rely on.
 *
 * The security-critical property is that drilling reuses baseSalesOrderQuery(),
 * so applySalesRepScope() still applies — a Sales Rep must not be able to reach
 * another rep's orders by passing an arbitrary id. receiptReport() builds its
 * query by hand and so must apply that scope explicitly.
 */
class SalesReportDrilldownTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customerA;
    private Customer $customerB;
    private Product $product;
    private Employee $repA;
    private Employee $repB;
    private string $batchCode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'unpaid'], [
            'name' => 'Unpaid', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        $this->admin = $this->makeUser(salesAdmin: true);

        $this->customerA = $this->makeCustomer('Customer A');
        $this->customerB = $this->makeCustomer('Customer B');

        $brand = ListBrand::create(['name' => 'Jasmine', 'is_active' => true]);
        $unit  = ListUnit::create(['name' => 'Sack', 'is_active' => true]);
        $this->product = Product::create([
            'code' => 'P-'.uniqid(), 'brand_id' => $brand->id, 'unit_id' => $unit->id,
            'weight' => 25, 'is_active' => true,
        ]);

        // sales_order_items.batch_code is a FK onto inventory_stocks.batch_code,
        // so line items need a real batch to point at.
        $this->batchCode = 'BATCH-'.uniqid();
        \Illuminate\Support\Facades\DB::table('inventory_stocks')->insert([
            'batch_code' => $this->batchCode,
            'product_id' => $this->product->id,
            'quantity'   => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Deliberately high, explicit ids. sales_orders.added_by_id is a FK onto
        // users, but applySalesRepScope() compares it against an *employee* id,
        // so a low auto-increment employee id can coincidentally equal a user id
        // and make scoping look like it works when it is really just colliding.
        $this->repA = $this->makeEmployee('Alpha', 9001);
        $this->repB = $this->makeEmployee('Bravo', 9002);
    }

    private function makeEmployee(string $lastname, ?int $id = null): Employee
    {
        $employee = new Employee([
            'firstname' => 'Rep', 'lastname' => $lastname,
            'mobile' => '0900'.random_int(1000000, 9999999),
            'birthdate' => '1990-01-01', 'sex' => 'male', 'religion' => 'n/a',
        ]);

        // Set outside the constructor: `id` is not fillable.
        if ($id !== null) {
            $employee->id = $id;
        }

        $employee->save();

        return $employee;
    }

    private function makeUser(bool $salesAdmin, ?Employee $employee = null): User
    {
        $user = User::factory()->create();

        // The link lives on employees.user_id (User::employee() is a hasOne),
        // which is what applySalesRepScope() resolves to scope a rep.
        $employee?->update(['user_id' => $user->id]);

        $role = ListRole::create([
            'name' => 'Role '.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true,
        ]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($salesAdmin) {
            // Module-wide admin on sales: this is what applySalesRepScope()
            // checks to decide whether to lift the per-rep restriction.
            RolePermission::create([
                'role_id'      => $role->id,
                'module_id'    => Module::where('key', 'sales')->firstOrFail()->id,
                'submodule_id' => null,
                'access_level' => 'admin',
            ]);
        }

        return $user;
    }

    private function makeCustomer(string $name): Customer
    {
        return Customer::create([
            'name' => $name, 'address' => 'Addr', 'contact_number' => '0900'.random_int(1000000, 9999999),
            'added_by_id' => $this->admin->id ?? 1, 'is_active' => true,
            'is_regular' => false, 'is_blacklisted' => false,
        ]);
    }

    /**
     * Orders default to the first of the month rather than today: order_date is
     * a datetime stored at midnight, and SQLite compares it to the `to` filter
     * as a raw string, so an order dated exactly on `to` sorts after it and is
     * excluded. MySQL casts and matches, so this is a test-environment quirk —
     * dating fixtures inside the range keeps the test about the drill-down.
     */
    private function makeOrder(?Customer $customer, ?Employee $rep, float $total, int $qty = 0, ?string $date = null, ?int $addedByUserId = null): SalesOrder
    {
        $order = SalesOrder::create([
            'so_number'    => 'SO-'.uniqid(),
            'order_date'   => $date ?: now()->startOfMonth()->toDateString(),
            'customer_id'  => $customer?->id,
            'sales_rep_id' => $rep?->id,
            'status_id'    => ListStatus::where('slug', 'unpaid')->first()->id,
            'total_amount' => $total,
            'payment_mode' => 'cash',
            'added_by_id'  => $addedByUserId ?: $this->admin->id,
        ]);

        if ($qty > 0) {
            // Via the relation, as SalesOrderClass does: SalesOrderItem::$fillable
            // is missing 'sales_order_id' (it has a stray 't' instead), so a
            // direct create() would silently drop the foreign key.
            $order->items()->create([
                'product_id'        => $this->product->id,
                'quantity'          => $qty,
                'price'             => $total / $qty,
                'discount_per_unit' => 0,
                'price_type'        => 'retail',
                'batch_code'        => $this->batchCode,
            ]);
        }

        return $order;
    }

    private function drill(array $params)
    {
        return $this->getJson('/reports?'.http_build_query(array_merge([
            'option' => 'drilldown',
            'from'   => now()->startOfMonth()->toDateString(),
            'to'     => now()->toDateString(),
        ], $params)));
    }

    public function test_customer_drilldown_returns_only_that_customers_orders(): void
    {
        $this->actingAs($this->admin);
        $this->makeOrder($this->customerA, null, 1000);
        $this->makeOrder($this->customerA, null, 500);
        $this->makeOrder($this->customerB, null, 9999);

        $res = $this->drill(['type' => 'customer', 'id' => $this->customerA->id])->assertOk();

        $res->assertJsonPath('mode', 'orders')
            ->assertJsonPath('context.label', 'Customer A')
            ->assertJsonPath('totals.orders', 2)
            ->assertJsonPath('totals.sales', 1500);
        $this->assertCount(2, $res->json('rows'));
    }

    public function test_product_drilldown_aggregates_quantity_per_order(): void
    {
        $this->actingAs($this->admin);
        $this->makeOrder($this->customerA, null, 1000, qty: 10);
        $this->makeOrder($this->customerB, null, 500, qty: 5);
        $this->makeOrder($this->customerA, null, 700); // no line items

        $res = $this->drill(['type' => 'product', 'id' => $this->product->id])->assertOk();

        $res->assertJsonPath('totals.orders', 2)
            ->assertJsonPath('totals.quantity', 15)
            ->assertJsonPath('context.label', 'Jasmine 25 Sack');
    }

    public function test_sales_rep_drilldown_returns_only_that_reps_orders(): void
    {
        $this->actingAs($this->admin);
        $this->makeOrder($this->customerA, $this->repA, 1000);
        $this->makeOrder($this->customerB, $this->repB, 2000);

        $this->drill(['type' => 'sales_rep', 'id' => $this->repA->id])
            ->assertOk()
            ->assertJsonPath('totals.orders', 1)
            ->assertJsonPath('totals.sales', 1000)
            ->assertJsonPath('context.label', 'Rep Alpha');
    }

    public function test_null_id_resolves_walk_in_customer(): void
    {
        $this->actingAs($this->admin);
        $this->makeOrder(null, null, 250);
        $this->makeOrder($this->customerA, null, 999);

        $this->drill(['type' => 'customer'])
            ->assertOk()
            ->assertJsonPath('context.label', 'Walk-in Customer')
            ->assertJsonPath('totals.orders', 1)
            ->assertJsonPath('totals.sales', 250);
    }

    public function test_order_drilldown_returns_line_items_and_meta(): void
    {
        $this->actingAs($this->admin);
        $order = $this->makeOrder($this->customerA, $this->repA, 1000, qty: 10);

        $res = $this->drill(['type' => 'order', 'id' => $order->id])->assertOk();

        $res->assertJsonPath('mode', 'record')
            ->assertJsonPath('context.label', $order->so_number)
            ->assertJsonPath('totals.sales', 1000)
            ->assertJsonPath('totals.quantity', 10);
        $this->assertSame('Jasmine 25 Sack', $res->json('rows.0.product_name'));
    }

    public function test_date_range_filter_is_respected(): void
    {
        $this->actingAs($this->admin);
        $this->makeOrder($this->customerA, null, 1000);
        $this->makeOrder($this->customerA, null, 4000, date: now()->subMonths(3)->toDateString());

        $this->drill(['type' => 'customer', 'id' => $this->customerA->id])
            ->assertOk()
            ->assertJsonPath('totals.orders', 1)
            ->assertJsonPath('totals.sales', 1000);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->drill(['type' => 'sql_injection', 'id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    /**
     * The one that matters: a Sales Rep passing another rep's customer id must
     * not receive that rep's orders.
     */
    public function test_sales_rep_cannot_drill_into_another_reps_orders(): void
    {
        $this->actingAs($this->admin);
        $mine     = $this->makeOrder($this->customerA, $this->repA, 1000);
        $notMine  = $this->makeOrder($this->customerA, $this->repB, 5000);

        // A non-admin user tied to repA.
        $repUser = $this->makeUser(salesAdmin: false, employee: $this->repA);
        $this->assertNotSame($this->admin->id, $this->repA->id, 'Fixture must avoid the user-id/employee-id collision.');
        $this->actingAs($repUser);

        $res = $this->drill(['type' => 'customer', 'id' => $this->customerA->id])->assertOk();

        $ids = collect($res->json('rows'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids, 'Rep should see their own order.');
        $this->assertNotContains($notMine->id, $ids, "Rep must not see another rep's order.");
        $this->assertSame(1000, (int) $res->json('totals.sales'));

        // Reaching for the other rep's order directly must also come back empty.
        $this->drill(['type' => 'order', 'id' => $notMine->id])
            ->assertOk()
            ->assertJsonPath('context.label', 'Order not available')
            ->assertJsonPath('rows', []);
    }

    /**
     * Regression: sales_orders.added_by_id is a FK onto users, but the rep scope
     * used to compare it against an *employee* id. A rep whose employee id
     * coincided with an unrelated user id could therefore see that user's
     * orders. Employee ids here are deliberately low so they collide with the
     * user ids, which is exactly the situation that used to leak.
     */
    public function test_rep_scope_does_not_leak_orders_via_user_employee_id_collision(): void
    {
        $collidingRep = $this->makeEmployee('Collider', 1);
        $repUser = $this->makeUser(salesAdmin: false, employee: $collidingRep);

        // Created by a different user (id 1 == the colliding employee id),
        // and assigned to nobody. The rep must not see it.
        $foreign = $this->makeOrder($this->customerA, null, 7777, addedByUserId: 1);
        $this->assertSame(1, $collidingRep->id, 'Fixture must reproduce the id collision.');

        $this->actingAs($repUser);
        $res = $this->drill(['type' => 'customer', 'id' => $this->customerA->id])->assertOk();

        $ids = collect($res->json('rows'))->pluck('id')->all();
        $this->assertNotContains($foreign->id, $ids, 'Rep must not see an order merely because a user id matched their employee id.');
    }

    /** A rep must still see orders they personally created. */
    public function test_rep_sees_orders_they_created(): void
    {
        $repUser = $this->makeUser(salesAdmin: false, employee: $this->repA);
        $own = $this->makeOrder($this->customerA, null, 4242, addedByUserId: $repUser->id);

        $this->actingAs($repUser);
        $res = $this->drill(['type' => 'customer', 'id' => $this->customerA->id])->assertOk();

        $ids = collect($res->json('rows'))->pluck('id')->all();
        $this->assertContains($own->id, $ids, 'Rep should see an order they created via added_by_id.');
    }

    /** Build a receipt against an order so the Receipt report has something to scope. */
    private function makeReceipt(SalesOrder $order, float $amount): int
    {
        $statusId = ListStatus::where('slug', 'unpaid')->first()->id;

        $invoiceId = \Illuminate\Support\Facades\DB::table('ar_invoices')->insertGetId([
            'sales_order_id' => $order->id,
            'status_id'      => $statusId,
            'invoice_number' => 'INV-'.uniqid(),
            'invoice_date'   => $order->order_date,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return \Illuminate\Support\Facades\DB::table('receipts')->insertGetId([
            'ar_invoice_id'  => $invoiceId,
            'status_id'      => $statusId,
            'receipt_number' => 'OR-'.uniqid(),
            'receipt_date'   => $order->order_date,
            'amount_paid'    => $amount,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * receiptReport() is private and summary() cannot run under SQLite —
     * dailySalesOrders() uses MySQL's GROUP_CONCAT(... SEPARATOR ...), which
     * SQLite rejects — so call the method directly.
     */
    private function receiptReportRows(): array
    {
        $service = app(\App\Services\Modules\ReportClass::class);
        $method = new \ReflectionMethod($service, 'receiptReport');
        $method->setAccessible(true);

        return collect($method->invoke($service, [
            'from'         => now()->startOfMonth()->toDateString(),
            'to'           => now()->toDateString(),
            'location_id'  => null,
            'payment_mode' => 'all',
            'limit'        => 50,
        ]))->pluck('id')->all();
    }

    /**
     * receiptReport() does not go through baseSalesOrderQuery(), so without an
     * explicit applySalesRepScope() a rep sees every receipt in the business.
     */
    public function test_receipt_report_is_scoped_to_the_rep(): void
    {
        $mineOrder    = $this->makeOrder($this->customerA, $this->repA, 1000);
        $notMineOrder = $this->makeOrder($this->customerB, $this->repB, 5000);
        $mine    = $this->makeReceipt($mineOrder, 1000);
        $notMine = $this->makeReceipt($notMineOrder, 5000);

        // An admin sees both.
        $this->actingAs($this->admin);
        $adminIds = $this->receiptReportRows();
        $this->assertContains($mine, $adminIds);
        $this->assertContains($notMine, $adminIds);

        // The rep sees only their own.
        $repUser = $this->makeUser(salesAdmin: false, employee: $this->repA);
        $this->actingAs($repUser);
        $repIds = $this->receiptReportRows();

        $this->assertContains($mine, $repIds, 'Rep should see their own receipt.');
        $this->assertNotContains($notMine, $repIds, "Rep must not see another rep's receipt.");
    }

    /**
     * SalesOrderItem::$fillable had a stray 't' where 'sales_order_id' belonged,
     * so a direct create() silently dropped the foreign key.
     */
    public function test_sales_order_item_persists_sales_order_id_on_direct_create(): void
    {
        $order = $this->makeOrder($this->customerA, null, 500);

        $item = \App\Models\SalesOrderItem::create([
            'sales_order_id'    => $order->id,
            'product_id'        => $this->product->id,
            'quantity'          => 2,
            'price'             => 250,
            'discount_per_unit' => 0,
            'price_type'        => 'retail',
            'batch_code'        => $this->batchCode,
        ]);

        $this->assertSame($order->id, $item->fresh()->sales_order_id);
    }

    /**
     * The whole summary payload used to 500 under SQLite because
     * dailySalesOrders() used MySQL's GROUP_CONCAT(... SEPARATOR ...), so the
     * main report had no coverage at all. It is composed in PHP now.
     */
    public function test_summary_endpoint_returns_every_report_section(): void
    {
        $this->actingAs($this->admin);
        $order = $this->makeOrder($this->customerA, $this->repA, 1000, qty: 4, date: now()->toDateString());

        $res = $this->getJson('/reports?'.http_build_query([
            'option' => 'summary',
            'from'   => now()->startOfMonth()->toDateString(),
            'to'     => now()->toDateString(),
            'day'    => now()->toDateString(),
        ]))->assertOk();

        $res->assertJsonStructure([
            'top_customers',
            'top_products',
            'product_sales_report',
            'customer_sales_report',
            'sales_rep_report',
            'daily_sales_orders',
            'payment_summary',
            'receipt_report',
            'discount_summary',
            'tax_summary',
            'employee_summary',
        ]);

        $daily = collect($res->json('daily_sales_orders'))->firstWhere('id', $order->id);
        $this->assertNotNull($daily, 'Order dated today should appear in daily_sales_orders.');
        $this->assertSame('Jasmine 25 Sack x4', $daily['sold_products']);
    }

    /** An order with no line items still renders, with a placeholder. */
    public function test_daily_sales_orders_handles_an_order_with_no_items(): void
    {
        $this->actingAs($this->admin);
        $order = $this->makeOrder($this->customerA, null, 750, date: now()->toDateString());

        $res = $this->getJson('/reports?'.http_build_query([
            'option' => 'summary',
            'from'   => now()->startOfMonth()->toDateString(),
            'to'     => now()->toDateString(),
            'day'    => now()->toDateString(),
        ]))->assertOk();

        $daily = collect($res->json('daily_sales_orders'))->firstWhere('id', $order->id);
        $this->assertSame('-', $daily['sold_products']);
    }
}
