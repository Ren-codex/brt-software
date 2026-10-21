<?php

namespace Tests\Feature\Sales;

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
 * Every step of an order should say when it happened, and by whom.
 *
 * Four moments were missing. Closing was never stamped, so the last step of
 * the timeline had no date. The time a delivery was encoded lived inside the
 * submission, which the approval wipes — so confirming a delivery erased the
 * record of when it was typed in. The handover was borrowing the remittance
 * date, which can be days later. And the two approvals were stamped in the
 * database but never shown.
 */
class ProcessTimestampsTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
        ListStatus::firstOrCreate(['slug' => 'for-release'], [
            'name' => 'For Release', 'text_color' => '#fff', 'bg_color' => '#007678',
        ]);
        $this->approver = $this->approverUser();
    }

    private function approverUser(): User
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

    private function rep(): Employee
    {
        return Employee::firstOrCreate(
            ['mobile' => '09170000111'],
            ['firstname' => 'Fixture', 'lastname' => 'Rep', 'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None']
        );
    }

    private function steps(SalesOrder $order): array
    {
        return collect(
            $this->actingAs($this->user)->getJson('/sales-orders/'.$order->id.'/timeline')->assertOk()->json('steps')
        )->keyBy('key')->all();
    }

    public function test_closing_an_order_stamps_when(): void
    {
        $this->postCod([
            'payment_mode' => 'Cash',
            'delivery_date' => null,
            'driver_id' => null,
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->assertSame('closed', $order->fresh('status')->status->slug);
        $this->assertNotNull($order->fresh()->closed_at);

        // And the last step of the timeline finally carries a date.
        $this->assertNotNull($this->steps($order)['closed']['at']);
    }

    public function test_reopening_an_order_clears_it(): void
    {
        $this->postCod([
            'payment_mode' => 'Cash',
            'delivery_date' => null,
            'driver_id' => null,
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->assertNotNull($order->fresh()->closed_at);

        // Knocked back: it is not finished, so the date must not outlive it.
        $order->update(['status_id' => ListStatus::where('slug', 'for-turnover')->firstOrFail()->id]);

        $this->assertNull($order->fresh()->closed_at);
    }

    public function test_approving_a_delivery_keeps_when_it_was_encoded(): void
    {
        config(['sales.delivery_approval' => true]);
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered', 'collected_amount' => 3000, 'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        $encodedAt = $order->fresh()->delivery_encoded_at;
        $this->assertNotNull($encodedAt);

        $this->actingAs($this->approver)
            ->postJson('/sales-orders/'.$order->id.'/delivery-decision', ['decision' => 'approved'])
            ->assertOk();

        $order->refresh();
        // The submission is gone, as it should be; the time it was typed in is not.
        $this->assertNull($order->delivery_submission);
        $this->assertEquals($encodedAt, $order->delivery_encoded_at);
        $this->assertNotNull($order->delivery_approved_at);

        $step = $this->steps($order)['delivery-approval'];
        $this->assertSame('Delivery approved', $step['label']);
        $this->assertNotNull($step['at']);
        $this->assertStringContainsString('Encoded by', $step['detail']);
    }

    public function test_the_handover_is_stamped_and_shown(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $order->update(['sales_rep_id' => $this->rep()->id]);

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered', 'collected_amount' => 3000, 'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        $receipt = Receipt::where('receipt_type', 'payment')->firstOrFail();
        $this->assertNull($receipt->handed_over_at);

        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $this->rep()->id])
            ->assertOk();

        $this->assertNotNull($receipt->fresh()->handed_over_at);

        // The step shows the handover, not a remittance that has not happened.
        $step = $this->steps($order->fresh())['turnover'];
        $this->assertSame('Money turned in', $step['label']);
        $this->assertNotNull($step['at']);
    }

    public function test_the_credit_approval_is_a_step_of_its_own(): void
    {
        config(['sales.credit_approval' => true]);
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $waiting = $this->steps($order)['credit-approval'];
        $this->assertSame('Credit approval', $waiting['label']);
        $this->assertNull($waiting['at']);

        $this->actingAs($this->approver)
            ->postJson('/sales-orders/'.$order->id.'/credit-decision', ['decision' => 'approved'])
            ->assertOk();

        $approved = $this->steps($order->fresh())['credit-approval'];
        $this->assertSame('Credit approved', $approved['label']);
        $this->assertNotNull($approved['at']);
        $this->assertNotNull($approved['by']);
    }

    public function test_an_order_needing_no_approval_shows_no_approval_steps(): void
    {
        // COD: nobody has to bless it, and nothing was encoded for approval.
        $this->postCod()->assertSessionHasNoErrors();

        $steps = $this->steps(SalesOrder::firstOrFail());

        $this->assertArrayNotHasKey('credit-approval', $steps);
        $this->assertArrayNotHasKey('delivery-approval', $steps);
    }
}
