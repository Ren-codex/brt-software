<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
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
 * A customer paying part cash and part cheque at the door.
 *
 * The service has always taken the collection as lines; the delivery screen
 * could only send one tender, so a split had to be recorded as a single mode
 * and corrected later — or, worse, left wrong.
 */
class DoorSplitFromTheScreenTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
    }

    private function deliver(array $splits)
    {
        $this->postCod()->assertSessionHasNoErrors();

        return $this->actingAs($this->user)->put('/sales-orders/'.SalesOrder::firstOrFail()->id, [
            'action' => 'mark-delivered',
            'collected_splits' => $splits,
        ]);
    }

    public function test_part_cash_and_part_cheque_is_one_delivery_two_receipts(): void
    {
        $this->deliver([
            ['payment_mode' => 'Cash', 'amount' => 1000],
            ['payment_mode' => 'Check', 'amount' => 2000, 'reference_number' => 'CHK-77',
                'check_date' => now()->addDays(5)->toDateString()],
        ])->assertSessionHasNoErrors();

        $receipts = Receipt::where('receipt_type', 'payment')->get();
        $this->assertCount(2, $receipts);
        $this->assertEqualsCanonicalizing(['Cash', 'Check'], $receipts->pluck('payment_mode')->all());
        $this->assertSame(3000.0, (float) $receipts->sum('amount_paid'));

        // The cheque half is not money yet, so only the cash came off.
        $this->assertSame(2000.0, (float) ArInvoice::firstOrFail()->balance_due);
    }

    public function test_the_order_records_it_as_split(): void
    {
        $this->deliver([
            ['payment_mode' => 'Cash', 'amount' => 1500],
            ['payment_mode' => 'Bank Transfer', 'amount' => 1500, 'reference_number' => 'TRN-9', 'bank_account_id' => $this->fixtureBank()->id],
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(SalesOrder::firstOrFail()->delivered_at);
        $this->assertCount(2, Receipt::where('receipt_type', 'payment')->get());
    }

    public function test_the_lines_together_still_have_to_cover_a_cod_order(): void
    {
        // The COD rule reads the total of the lines, not any one of them.
        $this->deliver([
            ['payment_mode' => 'Cash', 'amount' => 1000],
            ['payment_mode' => 'Check', 'amount' => 500, 'reference_number' => 'CHK-78',
                'check_date' => now()->addDays(5)->toDateString()],
        ])->assertSessionHasErrors('collected_amount');

        $this->assertNull(SalesOrder::firstOrFail()->delivered_at);
        $this->assertSame(0, Receipt::where('receipt_type', 'payment')->count());
    }

    public function test_the_lines_survive_an_approval(): void
    {
        // Where deliveries are approved, encoding only files the payload away.
        // The split has to still be a split when the approver confirms it,
        // days later, or the second tender quietly disappears.
        config(['sales.delivery_approval' => true]);
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'collected_splits' => [
                ['payment_mode' => 'Cash', 'amount' => 1000],
                ['payment_mode' => 'Check', 'amount' => 2000, 'reference_number' => 'CHK-79',
                    'check_date' => now()->addDays(5)->toDateString()],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Receipt::where('receipt_type', 'payment')->count(), 'Nothing recorded yet.');

        $this->actingAs($this->approver())
            ->postJson('/sales-orders/'.$order->id.'/delivery-decision', ['decision' => 'approved'])
            ->assertOk();

        $receipts = Receipt::where('receipt_type', 'payment')->get();
        $this->assertCount(2, $receipts);
        $this->assertEqualsCanonicalizing(['Cash', 'Check'], $receipts->pluck('payment_mode')->all());
        $this->assertSame(2000.0, (float) ArInvoice::firstOrFail()->balance_due, 'Only the cash is money.');
    }

    private function approver(): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R'.uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'sales_orders')->firstOrFail()->id,
            'access_level' => 'approver',
        ]);

        return $user;
    }

    public function test_an_empty_line_is_ignored_rather_than_refused(): void
    {
        // The screen starts a second line blank; leaving it so must not stop
        // a delivery paid with one tender.
        $this->deliver([
            ['payment_mode' => 'Cash', 'amount' => 3000],
            ['payment_mode' => 'Cash', 'amount' => null],
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, Receipt::where('receipt_type', 'payment')->get());
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);
    }
}
