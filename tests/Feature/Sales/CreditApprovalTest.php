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
 * A sale on credit is saved, but held: no delivery and no payment until an
 * approver clears it, and refusing it cancels the order.
 */
class CreditApprovalTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sales.credit_approval' => true, 'sales.delivery_approval' => false]);
        $this->seedCodFixture();
        $this->approver = $this->salesOrderApprover();
    }

    private function salesOrderApprover(): User
    {
        $role = ListRole::create(['name' => 'Approver '.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'sales_orders')->firstOrFail()->id,
            'access_level' => 'approver',
        ]);

        return $user;
    }

    private function deliver(SalesOrder $order, array $payload = [])
    {
        return $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, array_merge(['action' => 'mark-delivered'], $payload));
    }

    private function decide(SalesOrder $order, string $decision, ?User $as = null, array $extra = [])
    {
        return $this->actingAs($as ?? $this->approver)
            ->postJson('/sales-orders/'.$order->id.'/credit-decision', ['decision' => $decision] + $extra);
    }

    public function test_a_credit_order_is_saved_waiting_for_approval(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();

        $this->assertTrue(SalesOrder::firstOrFail()->awaitsCreditApproval());
    }

    public function test_a_cod_order_needs_no_approval(): void
    {
        $this->postCod()->assertSessionHasNoErrors();

        $this->assertNull(SalesOrder::firstOrFail()->credit_approval);
    }

    public function test_it_cannot_be_delivered_until_approved(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->deliver($order)->assertSessionHasErrors('delivered_at');
        $this->assertNull($order->fresh()->delivered_at);

        $this->decide($order, 'approved')->assertOk();

        $this->deliver($order)->assertSessionHasNoErrors();
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_it_cannot_take_a_payment_until_approved(): void
    {
        $this->grantArInvoiceAccess($this->user);
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $invoice = $order->arInvoices()->firstOrFail();

        $pay = fn () => $this->actingAs($this->user)->from('/ar-invoices')->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'action' => 'payment',
            'balance_due' => $invoice->balance_due,
            'payment_date' => now()->toDateString(),
            'splits' => [['payment_mode' => 'Cash', 'amount' => 1000]],
        ]);

        $pay()->assertSessionHasErrors('splits');
        $this->assertEquals((float) $invoice->balance_due, (float) $invoice->fresh()->balance_due);

        $this->decide($order, 'approved')->assertOk();

        $pay()->assertSessionHasNoErrors();
        $this->assertEquals((float) $invoice->balance_due - 1000, (float) $invoice->fresh()->balance_due);
    }

    public function test_refusing_the_credit_cancels_the_order(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->decide($order, 'disapproved', null, ['remarks' => 'Over their limit'])->assertOk();

        $order->refresh();
        $this->assertSame('rejected', $order->credit_approval);
        $this->assertSame('cancelled', $order->status->slug);
        $this->assertStringContainsString('Over their limit', $order->credit_approval_remarks);
    }

    public function test_an_encoder_cannot_approve_credit(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->decide($order, 'approved', $this->user)->assertForbidden();

        $this->assertTrue($order->fresh()->awaitsCreditApproval());
    }

    public function test_it_can_be_approved_from_the_approvals_page(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $list = $this->actingAs($this->approver)->getJson('/approvals?option=lists')->assertOk();
        $this->assertStringContainsString($order->so_number, $list->getContent());

        $this->actingAs($this->approver)
            ->postJson('/approvals/credit_order/'.$order->id.'/decide', ['decision' => 'approved'])
            ->assertOk();

        $this->assertSame('approved', $order->fresh()->credit_approval);
    }
}
