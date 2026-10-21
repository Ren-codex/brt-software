<?php

namespace Tests\Feature\Sales;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * A credit sale nobody has approved must not produce paperwork.
 *
 * The printed order is what the warehouse releases goods against and what the
 * customer is handed, and it looked exactly the same approved or not — the
 * page says nothing about credit at all. So the one thing the approval exists
 * to stop, goods going out on credit nobody agreed to, could be arranged with
 * a document the system itself printed.
 *
 * Nothing else changes: COD, cash, and credit once approved all print as
 * before.
 */
class UnapprovedCreditDoesNotPrintTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sales.credit_approval' => true]);
        $this->seedCodFixture();
    }

    private function printOrder(SalesOrder $order)
    {
        return $this->actingAs($this->user)
            ->get('/sales-orders/'.$order->id.'?option=print&type=sales_order');
    }

    private function approver(): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'A'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'sales_orders')->firstOrFail()->id,
            'access_level' => 'approver',
        ]);

        return $user;
    }

    public function test_a_credit_order_waiting_for_approval_does_not_print(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->assertTrue($order->awaitsCreditApproval());

        $this->printOrder($order)->assertForbidden();
    }

    public function test_approving_it_lets_it_print(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->approver())
            ->postJson('/sales-orders/'.$order->id.'/credit-decision', ['decision' => 'approved'])
            ->assertOk();

        $response = $this->printOrder($order->fresh());
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_an_approver_cannot_print_it_either(): void
    {
        // Not a permission question: the order is not fit to be a document
        // yet, whoever is asking for it.
        $this->postCredit()->assertSessionHasNoErrors();

        $this->actingAs($this->approver())
            ->get('/sales-orders/'.SalesOrder::firstOrFail()->id.'?option=print&type=sales_order')
            ->assertForbidden();
    }

    public function test_cod_and_cash_orders_are_untouched(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        // COD needs nobody's approval, so there is nothing to wait for.
        $this->assertFalse($order->awaitsCreditApproval());
        $this->printOrder($order)->assertOk();
    }

    public function test_a_refused_credit_order_does_not_print_either(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->approver())
            ->postJson('/sales-orders/'.$order->id.'/credit-decision', ['decision' => 'disapproved', 'remarks' => 'Over the limit'])
            ->assertOk();

        // Refusing cancels the order. Printing it would hand someone a
        // document for a sale that is not happening.
        $this->printOrder($order->fresh())->assertForbidden();
    }
}
