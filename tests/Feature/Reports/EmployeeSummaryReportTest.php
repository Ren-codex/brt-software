<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListBrand;
use App\Models\ListStatus;
use App\Models\ListUnit;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for punch-list item #19: the employee summary report's
 * "sold quantity" used to divide total kg sold by 25 with no unit label and
 * never computed a percentage share. Now it divides by 50 (a 50kg sack) and
 * adds a `percentage` field — each rep's share of the sold_quantity across
 * every rep in the filtered period, not just what that viewer can see.
 */
class EmployeeSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'unpaid'], [
            'name' => 'Unpaid', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        $this->admin = User::factory()->create();

        $this->customer = Customer::create([
            'name' => 'Customer', 'address' => 'Addr', 'contact_number' => '0900'.random_int(1000000, 9999999),
            'added_by_id' => $this->admin->id, 'is_active' => true,
            'is_regular' => false, 'is_blacklisted' => false,
        ]);

        $brand = ListBrand::create(['name' => 'Jasmine', 'is_active' => true]);
        $unit = ListUnit::create(['name' => 'Sack', 'is_active' => true]);
        $this->product = Product::create([
            'code' => 'P-'.uniqid(), 'brand_id' => $brand->id, 'unit_id' => $unit->id,
            'weight' => 50, 'is_active' => true,
        ]);

        $batchCode = 'BATCH-'.uniqid();
        \Illuminate\Support\Facades\DB::table('inventory_stocks')->insert([
            'batch_code' => $batchCode, 'product_id' => $this->product->id,
            'quantity' => 1000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->batchCode = $batchCode;
    }

    private function makeEmployee(string $lastname): Employee
    {
        return Employee::create([
            'firstname' => 'Rep', 'lastname' => $lastname,
            'mobile' => '0900'.random_int(1000000, 9999999),
            'birthdate' => '1990-01-01', 'sex' => 'male', 'religion' => 'n/a',
        ]);
    }

    private function makeOrder(Employee $rep, int $qty): SalesOrder
    {
        $order = SalesOrder::create([
            'so_number' => 'SO-'.uniqid(),
            'order_date' => now()->startOfMonth()->toDateString(),
            'customer_id' => $this->customer->id,
            'sales_rep_id' => $rep->id,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
            'total_amount' => $qty * 100,
            'payment_mode' => 'cash',
            'added_by_id' => $this->admin->id,
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'price' => 100,
            'discount_per_unit' => 0,
            'price_type' => 'retail',
            'batch_code' => $this->batchCode,
        ]);

        return $order;
    }

    public function test_sold_quantity_divides_by_50kg_and_percentage_reflects_share_of_total(): void
    {
        $repA = $this->makeEmployee('Alpha');
        $repB = $this->makeEmployee('Bravo');

        // 50kg product: repA sells 6 units => 300kg => 6 "sacks"; repB sells 2 => 100kg => 2 sacks.
        $this->makeOrder($repA, 6);
        $this->makeOrder($repB, 2);

        $this->actingAs($this->admin);

        $res = $this->getJson('/reports?'.http_build_query([
            'option' => 'summary',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk();

        $rows = collect($res->json('employee_summary'));
        $rowA = $rows->firstWhere('employee_id', $repA->id);
        $rowB = $rows->firstWhere('employee_id', $repB->id);

        $this->assertEquals(6.0, $rowA['sold_quantity']);
        $this->assertEquals(2.0, $rowB['sold_quantity']);

        // Total sold_quantity across both reps is 8 sacks: repA is 75%, repB is 25%.
        $this->assertEquals(75.0, $rowA['percentage']);
        $this->assertEquals(25.0, $rowB['percentage']);
    }
}
