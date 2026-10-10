<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Closed should mean the business is finished with an order, and it is not
 * finished while the money is in somebody's pocket.
 *
 * A COD order used to flip to Closed the moment the driver took the cash. It
 * now stops at For Turnover and closes when the driver hands the money in —
 * the point the business actually receives it.
 *
 * Nothing in the field means nothing to wait for: a counter sale, or a credit
 * customer paying at the office, closes as soon as it is paid and delivered.
 */
class ClosesOnTurnoverTest extends TestCase
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

    private function deliverAndCollect(): SalesOrder
    {
        $order = SalesOrder::firstOrFail();
        // The money can only go to this order's own rep, so the order needs one.
        $order->update(['sales_rep_id' => $this->salesRepId()]);

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'delivered_at' => now()->toDateString(),
            'collected_amount' => 3000,
            'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        return $order->fresh('status');
    }

    private function handOverTo(int $employeeId)
    {
        return $this->actingAs($this->user)
            ->putJson('/receipts/'.Receipt::firstOrFail()->id.'/turn-over', [
                'held_by_employee_id' => $employeeId,
            ]);
    }

    public function test_a_cod_collection_stops_at_for_turnover(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = $this->deliverAndCollect();

        $this->assertSame('for-turnover', $order->status->slug);
        $this->assertSame(0.0, (float) ArInvoice::firstOrFail()->balance_due);
    }

    public function test_the_money_is_still_counted_as_out(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->deliverAndCollect();

        $this->assertSame($this->fixtureDriverId(), Receipt::firstOrFail()->held_by_employee_id);
        $this->assertFalse(SalesOrder::firstOrFail()->moneyIsIn());
    }

    public function test_handing_it_in_closes_the_order(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->deliverAndCollect();

        $this->handOverTo($this->salesRepId())->assertOk();

        $this->assertSame('closed', SalesOrder::firstOrFail()->fresh('status')->status->slug);
        $this->assertTrue(SalesOrder::firstOrFail()->moneyIsIn());
    }

    public function test_it_cannot_be_handed_back_to_the_driver(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->deliverAndCollect();
        $this->handOverTo($this->salesRepId())->assertOk();

        // Money goes to the rep whose sale it is, never back out to the driver.
        $this->handOverTo($this->fixtureDriverId())->assertStatus(422);

        $this->assertSame('closed', SalesOrder::firstOrFail()->fresh('status')->status->slug);
        $this->assertSame($this->salesRepId(), Receipt::firstOrFail()->held_by_employee_id);
    }

    public function test_a_counter_sale_closes_without_a_handover(): void
    {
        // No driver, so the money never left the office.
        $this->postCod([
            'payment_mode' => 'Cash',
            'delivery_date' => null,
            'driver_id' => null,
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('closed', SalesOrder::firstOrFail()->fresh('status')->status->slug);
    }

    public function test_a_credit_customer_paying_at_the_office_closes_at_once(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'delivered_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $invoice = ArInvoice::firstOrFail();
        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => (float) $invoice->balance_due,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        // Nobody carried it anywhere, so there is nothing to hand in.
        $this->assertSame('closed', SalesOrder::firstOrFail()->fresh('status')->status->slug);
    }

    public function test_paid_but_undelivered_is_still_for_release(): void
    {
        $this->postCod([
            'payment_mode' => 'Cash',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ])->assertSessionHasNoErrors();

        // Money in, sacks not gone: the other half-finished state.
        $this->assertSame('for-release', SalesOrder::firstOrFail()->fresh('status')->status->slug);
    }

    private function fixtureDriverId(): int
    {
        return SalesOrder::firstOrFail()->driver_id;
    }

    /** Somebody in the office for the driver to hand the money to. */
    private function salesRepId(): int
    {
        return \App\Models\Employee::firstOrCreate(
            ['mobile' => '09170000111'],
            [
                'firstname' => 'Fixture', 'lastname' => 'Rep',
                'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            ]
        )->id;
    }
}
