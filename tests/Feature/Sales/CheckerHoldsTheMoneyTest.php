<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * A delivery goes out as a pair, and the checker is the one who takes the money.
 *
 * Before an order could name a checker, every doorstep collection was recorded
 * against the driver — so the custody trail blamed the person at the wheel for
 * cash their partner was carrying. With a checker on the order the collection is
 * theirs; without one, nothing changes.
 */
class CheckerHoldsTheMoneyTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    private Employee $driver;

    private Employee $checker;

    private Employee $rep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);

        ListStatus::firstOrCreate(['slug' => 'for-release'], [
            'name' => 'For Release', 'text_color' => '#fff', 'bg_color' => '#007678',
        ]);

        $this->driver = $this->employee('Ana');
        $this->checker = $this->employee('Carl');
        $this->rep = $this->employee('Bea');
        $this->rep->update(['user_id' => $this->user->id]);
    }

    private function employee(string $firstname): Employee
    {
        return Employee::create([
            'firstname' => $firstname, 'lastname' => 'Cruz', 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
        ]);
    }

    private function collect(): Receipt
    {
        $invoice = ArInvoice::firstOrFail();
        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => (float) $invoice->balance_due,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        return Receipt::firstOrFail();
    }

    public function test_the_checker_is_saved_on_the_order(): void
    {
        $this->postCod([
            'driver_id' => $this->driver->id,
            'checker_id' => $this->checker->id,
            'sales_rep_id' => $this->rep->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->checker->id, SalesOrder::firstOrFail()->checker_id);
    }

    public function test_a_doorstep_collection_is_held_by_the_checker(): void
    {
        $this->postCod([
            'driver_id' => $this->driver->id,
            'checker_id' => $this->checker->id,
            'sales_rep_id' => $this->rep->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->checker->id, $this->collect()->held_by_employee_id);
    }

    public function test_without_a_checker_the_driver_still_holds_it(): void
    {
        $this->postCod(['driver_id' => $this->driver->id, 'sales_rep_id' => $this->rep->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->driver->id, $this->collect()->held_by_employee_id);
    }

    public function test_money_with_the_checker_is_not_yet_in(): void
    {
        $this->postCod([
            'driver_id' => $this->driver->id,
            'checker_id' => $this->checker->id,
            'sales_rep_id' => $this->rep->id,
        ])->assertSessionHasNoErrors();

        $this->collect();

        // Still out with the crew, so the order must not read as finished.
        $this->assertFalse(SalesOrder::firstOrFail()->moneyIsIn());
    }

    public function test_the_checker_cannot_also_be_the_driver(): void
    {
        $this->postCod([
            'driver_id' => $this->driver->id,
            'checker_id' => $this->driver->id,
            'sales_rep_id' => $this->rep->id,
        ])->assertSessionHasErrors('checker_id');
    }
}
