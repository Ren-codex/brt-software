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
 * Who is carrying the money a payment records.
 *
 * Normally the system knows: a COD collection happens at the door, so the
 * crew has it until they hand it in. But a payment recorded to correct an
 * earlier mistake is typed at the office, days later, with the cash sitting
 * in front of whoever is fixing it — and assuming the driver has it sends the
 * rep off to record a handover from somebody who never touched it.
 *
 * So the screen may say who holds it, and says nothing different when it has
 * nothing to add.
 */
class WhoHoldsAPaymentTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    private Employee $rep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);

        $this->rep = Employee::firstOrCreate(
            ['mobile' => '09170000111'],
            ['firstname' => 'Bea', 'lastname' => 'Rep', 'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None']
        );
    }

    private function codOrder(): SalesOrder
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $order->update(['sales_rep_id' => $this->rep->id]);

        return $order->fresh();
    }

    private function pay(array $extra = [])
    {
        $invoice = ArInvoice::firstOrFail();

        return $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, array_merge([
            'id' => $invoice->id,
            'option' => 'payment',
            'action' => 'payment',
            'payment_date' => now()->toDateString(),
            'balance_due' => (float) $invoice->balance_due,
            'splits' => [['payment_mode' => 'Cash', 'amount' => 3000]],
        ], $extra));
    }

    public function test_saying_who_holds_it_puts_it_with_them(): void
    {
        $this->codOrder();

        $this->pay(['held_by_employee_id' => $this->rep->id])->assertSessionHasNoErrors();

        $this->assertSame($this->rep->id, Receipt::where('receipt_type', 'payment')->firstOrFail()->held_by_employee_id);
    }

    public function test_saying_nothing_leaves_the_rule_as_it_was(): void
    {
        $order = $this->codOrder();

        $this->pay()->assertSessionHasNoErrors();

        // COD collects at the door, so the crew carries it until it is handed in.
        $this->assertSame($order->driver_id, Receipt::where('receipt_type', 'payment')->firstOrFail()->held_by_employee_id);
    }

    public function test_an_office_payment_on_credit_still_sits_with_the_rep(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        SalesOrder::firstOrFail()->update(['sales_rep_id' => $this->rep->id]);

        $this->pay()->assertSessionHasNoErrors();

        $this->assertSame($this->rep->id, Receipt::where('receipt_type', 'payment')->firstOrFail()->held_by_employee_id);
    }

    public function test_a_holder_who_does_not_exist_is_refused(): void
    {
        $this->codOrder();

        $this->pay(['held_by_employee_id' => 99999])->assertSessionHasErrors('held_by_employee_id');

        $this->assertSame(0, Receipt::where('receipt_type', 'payment')->count());
    }

    public function test_the_correction_lands_with_the_rep_who_has_the_cash(): void
    {
        // The case this exists for: a wrong mode voided, the payment recorded
        // again at the office, the money already in the rep's hands. Without
        // saying so, it would be assigned to the driver and need a second
        // handover from someone who never touched it.
        $order = $this->codOrder();

        $this->pay(['held_by_employee_id' => $this->rep->id])->assertSessionHasNoErrors();

        $receipt = Receipt::where('receipt_type', 'payment')->firstOrFail();
        $this->assertSame($this->rep->id, $receipt->held_by_employee_id);
        $this->assertNotSame($order->driver_id, $receipt->held_by_employee_id);
    }
}
