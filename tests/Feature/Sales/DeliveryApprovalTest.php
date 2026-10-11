<?php

namespace Tests\Feature\Sales;

use App\Models\ListRole;
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
 * Encoding a delivery only submits it. It is not delivered, and the money
 * entered with it is not a payment, until an approver confirms it.
 */
class DeliveryApprovalTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sales.delivery_approval' => true]);
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

    private function encode(SalesOrder $order, array $payload = [])
    {
        return $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, array_merge(['action' => 'mark-delivered'], $payload));
    }

    private function decide(SalesOrder $order, string $decision, ?User $as = null, array $extra = [])
    {
        return $this->actingAs($as ?? $this->approver)
            ->postJson('/sales-orders/'.$order->id.'/delivery-decision', ['decision' => $decision] + $extra);
    }

    public function test_encoding_a_delivery_leaves_it_pending_with_nothing_recorded(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->encode($order, ['collected_amount' => 3000, 'collected_mode' => 'Cash'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertNull($order->delivered_at);
        $this->assertTrue($order->awaitsDeliveryApproval());
        // The money was typed in, not taken in: no receipt until it is approved.
        $this->assertSame(0, Receipt::where('receipt_type', 'payment')->count());
        $this->assertEquals(3000, (float) $order->arInvoices()->first()->balance_due);
    }

    public function test_a_delivery_that_would_be_refused_is_refused_when_encoded(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        // COD with nothing collected: the encoder hears about it, not the approver.
        $this->encode($order)->assertSessionHasErrors('collected_amount');

        $this->assertFalse($order->fresh()->awaitsDeliveryApproval());
    }

    public function test_it_cannot_be_encoded_twice_while_it_waits(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->encode($order)->assertSessionHasNoErrors();
        $this->encode($order)->assertSessionHasErrors('delivered_at');
    }

    public function test_approving_records_the_delivery_and_the_payment(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->encode($order, ['collected_amount' => 3000, 'collected_mode' => 'Cash'])
            ->assertSessionHasNoErrors();

        $this->decide($order, 'approved')->assertOk();

        $order->refresh();
        $this->assertNotNull($order->delivered_at);
        $this->assertFalse($order->awaitsDeliveryApproval());
        // Delivered by whoever encoded it, approved by whoever confirmed it.
        $this->assertSame($this->user->id, $order->delivered_by_id);
        $this->assertSame($this->approver->id, $order->delivery_approved_by_id);
        $this->assertSame(1, Receipt::where('receipt_type', 'payment')->count());
        $this->assertEquals(3000, (float) Receipt::where('receipt_type', 'payment')->first()->amount_paid);
    }

    public function test_sending_it_back_records_nothing_and_says_why(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->encode($order, ['collected_amount' => 3000, 'collected_mode' => 'Cash'])
            ->assertSessionHasNoErrors();

        $this->decide($order, 'disapproved', null, ['remarks' => 'Wrong amount'])->assertOk();

        $order->refresh();
        $this->assertNull($order->delivered_at);
        $this->assertFalse($order->awaitsDeliveryApproval());
        $this->assertStringContainsString('Wrong amount', $order->delivery_rejection);
        $this->assertSame(0, Receipt::where('receipt_type', 'payment')->count());

        // And it can be encoded again.
        $this->encode($order, ['collected_amount' => 3000, 'collected_mode' => 'Cash'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($order->fresh()->awaitsDeliveryApproval());
    }

    public function test_an_encoder_cannot_approve(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->encode($order)->assertSessionHasNoErrors();

        $this->decide($order, 'approved', $this->user)->assertForbidden();

        $this->assertNull($order->fresh()->delivered_at);
    }

    public function test_the_board_holds_it_in_its_own_stage_until_approved(): void
    {
        $this->postCredit(['driver_id' => null, 'delivery_date' => now()->toDateString()])->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->encode($order)->assertSessionHasNoErrors();

        $board = $this->actingAs($this->user)->getJson('/sales-orders?option=delivery-board')->assertOk()->json();

        $this->assertSame([$order->id], array_column($board['pending_approval'], 'id'));
        $this->assertNotContains($order->id, array_column($board['out_for_delivery'], 'id'));
        $this->assertNotContains($order->id, array_column($board['to_collect'], 'id'));

        $this->decide($order, 'approved')->assertOk();

        $board = $this->actingAs($this->user)->getJson('/sales-orders?option=delivery-board')->assertOk()->json();
        $this->assertSame([], $board['pending_approval']);
        $this->assertContains($order->id, array_column($board['to_collect'], 'id'));
    }

    public function test_it_can_be_approved_from_the_approvals_page(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->encode($order)->assertSessionHasNoErrors();

        $this->actingAs($this->approver)
            ->postJson('/approvals/delivery/'.$order->id.'/decide', ['decision' => 'approved'])
            ->assertOk();

        $this->assertNotNull($order->fresh()->delivered_at);
    }
}
