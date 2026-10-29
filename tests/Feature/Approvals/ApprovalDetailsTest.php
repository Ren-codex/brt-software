<?php

namespace Tests\Feature\Approvals;

use App\Models\Check;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\InventoryStocks;
use App\Models\ListBrand;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\ListSupplier;
use App\Models\ListUnit;
use App\Models\Loan;
use App\Models\Module;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PettyCashFund;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Remittance;
use App\Models\ReplenishmentRequest;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The View button on the Approvals page: every source's details come back in
 * one shape, and only for sources the viewer may approve.
 */
class ApprovalDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['pending', 'approval', 'for-verification'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucwords(str_replace('-', ' ', $slug)), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function superAdmin(): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Super Admin'], ['type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        return $user;
    }

    private function details(User $as, string $source, int $id)
    {
        return $this->actingAs($as)->getJson("/approvals/{$source}/{$id}");
    }

    public function test_every_source_opens_in_the_same_shape(): void
    {
        $user = $this->superAdmin();
        $employee = Employee::create([
            'firstname' => 'Ana', 'lastname' => 'Cruz', 'mobile' => '09170000001',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None', 'user_id' => $user->id,
        ]);
        $pending = ListStatus::getBySlug('pending')->id;

        $records = Model::unguarded(function () use ($user, $employee, $pending) {
            $product = Product::create([
                'code' => 'JR-25', 'weight' => 25,
                'brand_id' => ListBrand::create(['name' => 'Jollyrice'])->id,
                'unit_id' => ListUnit::create(['name' => 'Sack'])->id,
            ]);
            $supplier = ListSupplier::create([
                'name' => 'Acme Mills', 'address' => 'Davao', 'contact_person' => 'Ben',
                'contact_number' => '0917', 'email' => 'acme@example.com',
            ]);

            $po = PurchaseOrder::create([
                'pr_number' => 'PR-1', 'po_date' => '2026-10-01', 'total_amount' => 2400,
                'status_id' => $pending, 'supplier_id' => $supplier->id, 'created_by_id' => $user->id,
            ]);
            $poItem = PurchaseOrderItem::create([
                'po_id' => $po->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 1200, 'total_cost' => 2400,
            ]);

            $return = StockReturn::create([
                'stock_return_no' => 'SR-1', 'po_id' => $po->id, 'reason' => 'Wet sacks',
                'status_id' => $pending, 'created_by_id' => $user->id,
            ]);
            StockReturnItem::create(['stock_return_id' => $return->id, 'po_item_id' => $poItem->id, 'quantity' => 1, 'status_id' => $pending]);

            $payroll = Payroll::create([
                'payroll_no' => 'PAY-1', 'pay_period_start' => '2026-10-01', 'pay_period_end' => '2026-10-15',
                'status_id' => ListStatus::getBySlug('approval')->id, 'total_amount' => 9000, 'created_by' => $user->id,
            ]);
            PayrollItem::create([
                'payroll_id' => $payroll->id, 'employee_id' => $employee->id, 'basic_salary' => 9000,
                'net_salary' => 9000, 'total_days' => 13,
            ]);

            $loan = Loan::create([
                'loan_no' => 'LN-1', 'employee_id' => $employee->id, 'loan_type' => 'salary', 'amount' => 5000,
                'interest_rate' => 0, 'term_months' => 2, 'status' => 'pending', 'added_by_id' => $user->id,
            ]);

            $expense = Expense::create([
                'voucher_no' => 'EV-1', 'expense_type' => 'supplies', 'amount' => 750,
                'expense_date' => '2026-10-02', 'payee' => 'National Bookstore', 'status' => 'draft', 'added_by_id' => $user->id,
            ]);

            $remittance = Remittance::create([
                'remittance_no' => 'REM-1', 'remittance_date' => '2026-10-03', 'summary' => [], 'total_amount' => 4700,
                'status_id' => ListStatus::getBySlug('for-verification')->id, 'created_by_id' => $user->id,
            ]);

            $check = Check::create([
                'direction' => Check::DIRECTION_RECEIVED, 'check_number' => '000123', 'check_date' => '2026-10-04',
                'amount' => 5000, 'bank_name' => 'BDO', 'status' => Check::STATUS_PENDING,
                'source_type' => 'receipt', 'source_id' => 1,
            ]);

            $order = SalesOrder::create([
                'so_number' => 'SO-1', 'payment_mode' => 'Credit', 'order_date' => '2026-10-05', 'total_amount' => 3000,
                'added_by_id' => $user->id, 'status_id' => $pending, 'credit_approval' => 'pending',
            ]);
            InventoryStocks::create([
                'batch_code' => 'B-1', 'product_id' => $product->id, 'quantity' => 100,
                'retail_price' => 1500, 'wholesale_price' => 1400, 'unit_cost' => 1200,
            ]);
            SalesOrderItem::create([
                'sales_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 1500,
                'discount_per_unit' => 0, 'price_type' => 'retail', 'batch_code' => 'B-1',
            ]);

            $replenishment = ReplenishmentRequest::create([
                'reference_no' => 'RPL-1', 'status' => 'submitted', 'total_amount' => 750, 'created_by_id' => $user->id,
                'fund_id' => PettyCashFund::create(['name' => 'Office fund', 'gl_code' => '1010'])->id,
            ]);

            return [
                'purchase_order' => [$po, 'Purchase Order', 1],
                'stock_return' => [$return, 'Stock Return', 1],
                'payroll' => [$payroll, 'Payroll', 1],
                'loan' => [$loan, 'Employee Loan', null],
                'expense' => [$expense, 'Expense', null],
                'remittance' => [$remittance, 'Remittance', null],
                'check' => [$check, 'Cheque', null],
                'credit_order' => [$order, 'Credit Sale', 1],
                'replenishment' => [$replenishment, 'Replenishment', null],
            ];
        });

        foreach ($records as $source => [$record, $title, $lineCount]) {
            $json = $this->details($user, $source, $record->id)->assertOk()->json();

            $this->assertSame($title, $json['title'], $source);
            $this->assertNotEmpty($json['reference'], $source);
            $this->assertNotEmpty($json['fields'], $source);
            $lineCount === null
                ? $this->assertNull($json['lines'], $source)
                : $this->assertCount($lineCount, $json['lines']['rows'], $source);
        }

        // Spot checks on what the lines say.
        $po = $this->details($user, 'purchase_order', $records['purchase_order'][0]->id)->json();
        $this->assertSame('Jollyrice 25 Sack', $po['lines']['rows'][0]['product']);
        $this->assertEquals(2400, $po['lines']['total']);

        $payroll = $this->details($user, 'payroll', $records['payroll'][0]->id)->json();
        $this->assertSame('Ana Cruz', $payroll['lines']['rows'][0]['employee']);
        $this->assertContains(['label' => 'Payroll clerk', 'value' => 'Ana Cruz', 'type' => 'text'], $payroll['fields']);
    }

    public function test_a_source_the_viewer_cannot_approve_is_refused(): void
    {
        $role = ListRole::create(['name' => 'Payroll Approver', 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        $module = Module::where('key', 'payroll')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'payroll_processing')->firstOrFail()->id,
            'access_level' => 'approver',
        ]);

        $remittance = Remittance::create([
            'remittance_no' => 'REM-2', 'remittance_date' => '2026-10-03', 'summary' => [], 'total_amount' => 100,
            'status_id' => ListStatus::getBySlug('for-verification')->id, 'created_by_id' => $user->id,
        ]);

        $this->details($user, 'remittance', $remittance->id)->assertForbidden();
    }
}
