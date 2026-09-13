<?php

namespace Tests\Feature\Permissions;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use App\Services\System\Permission\PermissionService;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rep filter was `->when($employeeId, ...)`, and `when(null, ...)` does
 * nothing. $employeeId was null for an administrator — intended — and also for
 * any user with no employee record attached, which was not. That second case
 * showed one rep every other rep's orders.
 *
 * Not knowing who somebody is must never mean showing them everything.
 */
class SalesScopeFailsClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['unpaid' => 'Unpaid', 'for-payment' => 'For Payment', 'pending' => 'Pending'] as $slug => $name) {
            ListStatus::firstOrCreate(['slug' => $slug], ['name' => $name, 'text_color' => '#fff', 'bg_color' => '#333']);
        }
    }

    private function userWith(string $level, ?Employee $employee = null): User
    {
        $user = User::factory()->create();
        $employee?->update(['user_id' => $user->id]);

        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        RolePermission::create([
            'role_id' => $role->id,
            'module_id' => Module::where('key', 'sales')->firstOrFail()->id,
            'submodule_id' => null,
            'access_level' => $level,
        ]);

        return $user;
    }

    private function rep(string $lastname): Employee
    {
        return Employee::create([
            'lastname' => $lastname, 'firstname' => 'Rep', 'mobile' => '0917' . random_int(1000000, 9999999),
            'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'N/A',
            'is_regular' => 1, 'is_blacklisted' => 0,
        ]);
    }

    private function orderFor(Employee $rep, User $creator): SalesOrder
    {
        $customer = Customer::create([
            'name' => 'C' . uniqid(), 'address' => 'Z', 'contact_number' => '09170000000',
            'is_active' => 1, 'added_by_id' => $creator->id,
        ]);

        $order = SalesOrder::create([
            'so_number' => 'SO-' . uniqid(), 'payment_mode' => 'Credit Sales',
            'order_date' => now()->toDateString(), 'total_amount' => 1000, 'total_discount' => 0,
            'customer_id' => $customer->id, 'added_by_id' => $creator->id,
            'sales_rep_id' => $rep->id, 'requires_batch_approval' => false,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        ArInvoice::create([
            'sales_order_id' => $order->id, 'invoice_number' => 'AR-' . uniqid(),
            'invoice_date' => now()->toDateString(), 'amount_due' => 1000,
            'amount_paid' => 0, 'balance_due' => 1000, 'total_discount' => 0,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
        ]);

        return $order;
    }

    public function test_a_user_with_no_employee_record_sees_nothing_rather_than_everything(): void
    {
        $mine = $this->rep('Santos');
        $owner = $this->userWith('admin');
        $this->orderFor($mine, $owner);
        $this->orderFor($this->rep('Cruz'), $owner);

        // A Sales Rep grant, but nobody attached the employee record.
        $orphan = $this->userWith('encoder');

        $scope = app(PermissionService::class)->salesScopeEmployeeId($orphan);

        $this->assertNotNull($scope, 'A null scope means "show everything" — never correct for a non-admin.');
        $this->assertSame(-1, $scope, 'An unknown person matches no rows.');
    }

    public function test_a_linked_rep_is_scoped_to_their_own_employee_id(): void
    {
        $rep = $this->rep('Santos');
        $user = $this->userWith('encoder', $rep);

        $this->assertSame($rep->id, app(PermissionService::class)->salesScopeEmployeeId($user));
    }

    public function test_a_sales_admin_is_not_scoped_at_all(): void
    {
        $this->assertNull(app(PermissionService::class)->salesScopeEmployeeId($this->userWith('admin')));
    }

    public function test_a_guest_sees_nothing(): void
    {
        $this->assertSame(-1, app(PermissionService::class)->salesScopeEmployeeId(null));
    }

    public function test_the_orphaned_user_gets_an_empty_sales_list(): void
    {
        $owner = $this->userWith('admin');
        $this->orderFor($this->rep('Santos'), $owner);
        $this->orderFor($this->rep('Cruz'), $owner);

        $orphan = $this->userWith('encoder');

        $body = $this->actingAs($orphan)
            ->getJson('/sales-orders?option=lists&count=50')
            ->assertOk()
            ->json('data');

        $this->assertCount(0, $body, 'Two other reps\' orders must not be visible to somebody the system cannot identify.');
    }
}
