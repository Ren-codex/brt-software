<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Cash in the Field listed every collection to whoever opened it, so one rep
 * could read another's outstanding money. A rep sees their own orders now —
 * including the ones a driver is still carrying, since those are theirs to
 * chase — and a sales administrator sees everything.
 */
class FieldCollectionsScopeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantRemittanceAccess($this->user);
    }

    private function employee(string $firstname): Employee
    {
        return Employee::create([
            'firstname' => $firstname, 'lastname' => 'Cruz', 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
        ]);
    }

    /** A collection on an order belonging to $rep, carried by $holder. */
    private function collection(Employee $rep, Employee $holder, string $soNumber, ?User $encodedBy = null): Receipt
    {
        $encodedBy = $encodedBy ?: $this->user;
        $order = SalesOrder::create([
            'so_number' => $soNumber,
            'order_date' => today()->toDateString(),
            'customer_id' => $this->customer->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->value('id'),
            'payment_mode' => 'COD',
            'sales_rep_id' => $rep->id,
            'total_amount' => 3000, 'total_discount' => 0,
            'added_by_id' => $encodedBy->id,
        ]);

        $invoice = ArInvoice::create([
            'sales_order_id' => $order->id,
            'invoice_number' => 'AR-'.uniqid(),
            'invoice_date' => today()->toDateString(),
            'amount_due' => 3000, 'amount_paid' => 3000, 'balance_due' => 0,
            'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'paid')->value('id'),
        ]);

        return Receipt::create([
            'receipt_number' => 'OR-'.uniqid(),
            'receipt_type' => 'payment',
            'receipt_date' => today()->toDateString(),
            'amount_paid' => 3000, 'balance_due' => 0,
            'payment_mode' => 'Cash',
            'status_id' => ListStatus::where('slug', 'pending')->value('id'),
            'customer_id' => $this->customer->id,
            'ar_invoice_id' => $invoice->id,
            'held_by_employee_id' => $holder->id,
        ]);
    }

    private function salesAdmin(): User
    {
        $role = ListRole::create(['name' => 'Sales Admin '.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => null, 'access_level' => 'admin',
        ]);
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'remittances')->firstOrFail()->id,
            'access_level' => 'view',
        ]);

        return $user;
    }

    private function rowsFor(User $user): array
    {
        return $this->actingAs($user)->getJson('/remittances?option=field-collections')->json();
    }

    public function test_an_order_i_encoded_is_mine_to_chase(): void
    {
        // Same rule the rest of the sales module uses: your own orders are the
        // ones you are the rep for, or the ones you encoded.
        $someoneElse = $this->employee('Carlo');
        $driver = $this->employee('Ana');

        $this->collection($someoneElse, $driver, 'SO-202609-0006');

        $this->assertCount(1, $this->rowsFor($this->user));
    }

    public function test_a_rep_sees_only_collections_on_their_own_orders(): void
    {
        $mine = $this->employee('Bea');
        $mine->update(['user_id' => $this->user->id]);
        $someoneElse = $this->employee('Carlo');
        $driver = $this->employee('Ana');

        $this->collection($mine, $driver, 'SO-202609-0001');
        // Another rep's order, encoded by them too: nothing ties it to me.
        $this->collection($someoneElse, $driver, 'SO-202609-0002', User::factory()->create());

        $rows = $this->rowsFor($this->user);

        $this->assertCount(1, $rows);
        $this->assertSame('SO-202609-0001', $rows[0]['so_number']);
    }

    public function test_a_rep_still_sees_their_money_while_a_driver_carries_it(): void
    {
        $mine = $this->employee('Bea');
        $mine->update(['user_id' => $this->user->id]);
        $driver = $this->employee('Ana');

        $this->collection($mine, $driver, 'SO-202609-0003');

        $rows = $this->rowsFor($this->user);

        $this->assertCount(1, $rows);
        $this->assertSame($driver->fullname, $rows[0]['holder']);
    }

    public function test_a_sales_administrator_sees_every_rep(): void
    {
        $repA = $this->employee('Bea');
        $repB = $this->employee('Carlo');
        $driver = $this->employee('Ana');

        $this->collection($repA, $driver, 'SO-202609-0004', User::factory()->create());
        $this->collection($repB, $driver, 'SO-202609-0005', User::factory()->create());

        $this->assertCount(2, $this->rowsFor($this->salesAdmin()));
    }
}
