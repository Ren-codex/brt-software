<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Cash a driver is carrying is the driver's to answer for, not the rep's.
 *
 * Remittance used to decide whose money it was from the order's sales rep, so
 * a rep was accountable for cash they had never touched — and could remit it
 * while it was still in a truck. It follows the holder now: the rep becomes
 * accountable when the driver hands it over.
 */
class DriverHoldsUntilHandoverTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    private Employee $driver;

    private Employee $rep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
        $this->grantRemittanceAccess($this->user);

        ListStatus::firstOrCreate(['slug' => 'for-release'], [
            'name' => 'For Release', 'text_color' => '#fff', 'bg_color' => '#007678',
        ]);
        ListStatus::firstOrCreate(['slug' => 'for-verification'], [
            'name' => 'For Verification', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
        Series::firstOrCreate(['slug' => 'remittance'], [
            'name' => 'Remittance', 'prefix' => 'RM-', 'starting_value' => 1, 'max_digit' => 4,
        ]);

        $this->driver = $this->employee('Ana');
        // The rep is the logged-in person: they are the one who remits.
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

    private function collectedCodReceipt(): Receipt
    {
        $this->postCod(['driver_id' => $this->driver->id, 'sales_rep_id' => $this->rep->id])
            ->assertSessionHasNoErrors();

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

    private function holdings(): array
    {
        return $this->actingAs($this->user)
            ->getJson('/remittances?option=my_holdings')
            ->json();
    }

    private function remit(Receipt $receipt)
    {
        return $this->actingAs($this->user)->post('/remittances', [
            'remittance_date' => now()->toDateString(),
            'summary' => [['payment_mode' => 'Cash', 'amount' => 3000]],
            'total_amount' => 3000,
            'receipts' => [$receipt->id],
        ]);
    }

    public function test_money_in_the_truck_is_not_the_reps_to_answer_for(): void
    {
        $receipt = $this->collectedCodReceipt();

        $this->assertSame($this->driver->id, $receipt->held_by_employee_id);
        $this->assertSame(0, (int) ($this->holdings()['receipt_count'] ?? 0));
    }

    public function test_the_rep_cannot_remit_what_the_driver_is_still_holding(): void
    {
        $receipt = $this->collectedCodReceipt();

        $this->remit($receipt)->assertSessionHasErrors('receipts');

        $this->assertNull($receipt->fresh()->remittance_id);
    }

    public function test_after_the_handover_it_becomes_the_reps_to_remit(): void
    {
        $receipt = $this->collectedCodReceipt();

        $this->actingAs($this->user)
            ->put('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $this->rep->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, (int) ($this->holdings()['receipt_count'] ?? 0));

        $this->remit($receipt)->assertSessionHasNoErrors();
        $this->assertNotNull($receipt->fresh()->remittance_id);
    }

    public function test_a_receipt_nobody_holds_still_belongs_to_its_rep(): void
    {
        // Collections recorded before custody was tracked must not be stranded.
        $receipt = $this->collectedCodReceipt();
        $receipt->update(['held_by_employee_id' => null]);

        $this->assertSame(1, (int) ($this->holdings()['receipt_count'] ?? 0));
        $this->remit($receipt)->assertSessionHasNoErrors();
    }
}
