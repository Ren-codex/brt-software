<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Each stage of the board is one question with one answer.
 *
 * A cheque used to show up twice: as a debt under "To collect", because an
 * uncleared cheque leaves the invoice balance at its full figure, and as a
 * collection under "With driver / checker". The same ₱3,000 read as two
 * different problems, and the first one was not a problem at all — the
 * customer had paid.
 *
 * Now a cheque sits in exactly one place: with whoever is carrying the paper
 * until it is handed in, then under "Waiting for the bank" until it clears.
 * "To collect" means nobody has paid.
 */
class WaitingForTheBankTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
        $this->grantReceiptApproval($this->user);
    }

    private function board(): array
    {
        return $this->actingAs($this->user)
            ->getJson('/sales-orders?option=delivery-board')
            ->assertOk()
            ->json();
    }

    private function rep(): Employee
    {
        return Employee::firstOrCreate(
            ['mobile' => '09170000111'],
            ['firstname' => 'Fixture', 'lastname' => 'Rep', 'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None']
        );
    }

    /** Delivered, and paid at the door with a cheque. */
    private function deliveredOnACheque(float $amount = 3000): SalesOrder
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $order->update(['sales_rep_id' => $this->rep()->id]);

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'collected_amount' => $amount,
            'collected_mode' => 'Check',
            'collected_reference' => 'CHK-001',
            'collected_check_date' => now()->addDays(10)->toDateString(),
        ])->assertSessionHasNoErrors();

        return $order->fresh();
    }

    public function test_a_cheque_in_the_truck_is_not_money_the_customer_owes(): void
    {
        $this->deliveredOnACheque();

        $board = $this->board();

        // The paper is still with the crew, so that is where it shows.
        $this->assertCount(1, $board['with_driver']);
        $this->assertSame('Check', $board['with_driver'][0]['mode']);
        $this->assertFalse($board['with_driver'][0]['confirmed']);
        // And it is not asked for a second time as a debt.
        $this->assertCount(0, $board['to_collect']);
        $this->assertCount(0, $board['awaiting_bank']);
    }

    public function test_handing_it_in_moves_it_to_the_bank(): void
    {
        $this->deliveredOnACheque();
        $receipt = Receipt::where('receipt_type', 'payment')->firstOrFail();

        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $this->rep()->id])
            ->assertOk();

        $board = $this->board();

        $this->assertCount(1, $board['awaiting_bank']);
        $this->assertSame($receipt->receipt_number, $board['awaiting_bank'][0]['reference']);
        $this->assertSame(3000.0, (float) $board['awaiting_bank'][0]['amount']);
        $this->assertSame(now()->addDays(10)->toDateString(), $board['awaiting_bank'][0]['due']);
        // One home each: no longer anybody's to carry in, still nobody's debt.
        $this->assertCount(0, $board['with_driver']);
        $this->assertCount(0, $board['to_collect']);
    }

    public function test_clearing_it_takes_it_off_the_board(): void
    {
        $this->deliveredOnACheque();
        $receipt = Receipt::where('receipt_type', 'payment')->firstOrFail();

        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $this->rep()->id])
            ->assertOk();
        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/confirm-check', [
                'bank_name' => 'BDO', 'check_date' => now()->toDateString(),
            ])->assertOk();

        $board = $this->board();

        $this->assertCount(0, $board['awaiting_bank']);
        $this->assertCount(0, $board['to_collect']);
        // Cleared money is ordinary money now, waiting only to be remitted.
        $this->assertCount(1, $board['with_driver']);
        $this->assertTrue($board['with_driver'][0]['confirmed']);
    }

    public function test_a_part_cheque_leaves_the_rest_to_collect(): void
    {
        // Credit, so the goods can go out with only part of it paid.
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $order->update(['sales_rep_id' => $this->rep()->id]);

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
        ])->assertSessionHasNoErrors();

        $invoice = ArInvoice::firstOrFail();
        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'action' => 'payment',
            'payment_date' => now()->toDateString(),
            'balance_due' => (float) $invoice->balance_due,
            'splits' => [[
                'payment_mode' => 'Check', 'amount' => 1000, 'bank_account_id' => null,
                'reference_number' => 'CHK-PART', 'check_date' => now()->addDays(10)->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        $board = $this->board();

        // ₱1,000 is with the bank; the customer still owes the other ₱2,000.
        $this->assertCount(1, $board['awaiting_bank']);
        $this->assertSame(1000.0, (float) $board['awaiting_bank'][0]['amount']);
        $this->assertCount(1, $board['to_collect']);
        $this->assertSame(2000.0, (float) $board['to_collect'][0]['amount']);
    }

    public function test_cash_is_untouched_by_any_of_this(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $order->update(['sales_rep_id' => $this->rep()->id]);

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered', 'collected_amount' => 3000, 'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        $board = $this->board();

        $this->assertCount(1, $board['with_driver']);
        $this->assertTrue($board['with_driver'][0]['confirmed']);
        $this->assertCount(0, $board['awaiting_bank']);
        $this->assertCount(0, $board['to_collect']);
    }

    public function test_an_unpaid_delivery_is_still_asked_for_in_full(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, ['action' => 'mark-delivered'])
            ->assertSessionHasNoErrors();

        $board = $this->board();

        $this->assertCount(1, $board['to_collect']);
        $this->assertSame(3000.0, (float) $board['to_collect'][0]['amount']);
        $this->assertCount(0, $board['awaiting_bank']);
    }
}
