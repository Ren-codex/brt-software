<?php

namespace Tests\Feature\Sales;

use App\Models\Employee;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Who answers for money taken across the counter.
 *
 * It used to be nobody: finalizeCashSale never set a holder, so counter sales
 * showed as Unassigned and fell outside every scoped user's own pending set —
 * the remittance screen refused them with "You may only remit your own pending
 * receipts."
 *
 * The rep who made the sale answers for it through to the remittance, so the
 * receipt is theirs even though the cashier took the cash. Naming the cashier
 * instead would deadlock it: she would be holding money that is not her sale,
 * the rep would own a sale he is not holding, and neither could remit.
 */
class CounterSaleHasAHolderTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
    }

    private function postCounterSale(array $overrides = [])
    {
        return $this->postCod(array_merge([
            'payment_mode' => 'Cash',
            'delivery_date' => null,
            'driver_id' => null,
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ], $overrides));
    }

    private function makeEmployee(string $first, ?int $userId = null): Employee
    {
        return Employee::create([
            'firstname' => $first, 'lastname' => 'Person',
            'mobile' => '0917'.random_int(1000000, 9999999),
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'user_id' => $userId,
        ]);
    }

    public function test_the_rep_holds_it_even_though_the_cashier_took_the_cash(): void
    {
        $cashier = $this->makeEmployee('Tilly', $this->user->id);
        $rep = $this->makeEmployee('Remy');

        $this->postCounterSale(['sales_rep_id' => $rep->id])->assertSessionHasNoErrors();

        $receipt = Receipt::firstOrFail();
        $this->assertSame($rep->id, $receipt->held_by_employee_id);
        $this->assertNotSame($cashier->id, $receipt->held_by_employee_id);
    }

    public function test_a_walk_in_with_no_rep_belongs_to_the_cashier(): void
    {
        $cashier = $this->makeEmployee('Tilly', $this->user->id);

        $this->postCounterSale()->assertSessionHasNoErrors();

        $this->assertSame($cashier->id, Receipt::firstOrFail()->held_by_employee_id);
    }

    public function test_with_nobody_to_name_the_order_still_records_who_booked_it(): void
    {
        // No rep, and a user with no employee record, so there is genuinely
        // nobody to put in the holder column. The remittance scope carries it
        // instead, through added_by_id — see RemittanceScopeTest.
        $this->postCounterSale()->assertSessionHasNoErrors();

        $receipt = Receipt::firstOrFail();
        $this->assertNull($receipt->held_by_employee_id);
        $this->assertSame($this->user->id, $receipt->arInvoice->sales_order->added_by_id);
    }
}
