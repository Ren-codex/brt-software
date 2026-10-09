<?php

namespace Tests\Feature\Sales;

use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Money taken across the counter belongs to the cashier who took it.
 *
 * It used to belong to nobody: finalizeCashSale never set a holder, so every
 * counter sale showed as Unassigned in Cash in the Field. That is not only
 * untidy — a receipt with no holder is outside every scoped user's own pending
 * set, so nobody but a sales administrator could remit it, and the remittance
 * screen refused it with "You may only remit your own pending receipts."
 */
class CounterSaleHasAHolderTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();

        // Remitting needs a document series and the status a remittance opens in.
        Series::firstOrCreate(['slug' => 'remittance'], [
            'name' => 'Remittance', 'prefix' => 'RM-', 'starting_value' => 1, 'max_digit' => 4,
        ]);
        ListStatus::firstOrCreate(['slug' => 'for-verification'], [
            'name' => 'For Verification', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
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

    public function test_the_cashier_holds_what_they_took(): void
    {
        $cashier = Employee::create([
            'firstname' => 'Tilly', 'lastname' => 'Cashier', 'mobile' => '09170000333',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'user_id' => $this->user->id,
        ]);

        $this->postCounterSale()->assertSessionHasNoErrors();

        $this->assertSame($cashier->id, Receipt::firstOrFail()->held_by_employee_id);
    }

    public function test_it_falls_back_to_the_sales_rep_when_the_user_is_not_an_employee(): void
    {
        $rep = Employee::create([
            'firstname' => 'Remy', 'lastname' => 'Rep', 'mobile' => '09170000444',
            'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'None',
        ]);

        $this->postCounterSale(['sales_rep_id' => $rep->id])->assertSessionHasNoErrors();

        $this->assertSame($rep->id, Receipt::firstOrFail()->held_by_employee_id);
    }

    /** The bug as the user met it: the remittance screen refusing the receipt. */
    public function test_the_cashier_can_now_remit_what_they_took(): void
    {
        $cashier = Employee::create([
            'firstname' => 'Tilly', 'lastname' => 'Cashier', 'mobile' => '09170000555',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'user_id' => $this->user->id,
        ]);
        $this->grantRemittanceAccess($this->user);

        $this->postCounterSale()->assertSessionHasNoErrors();
        $receipt = Receipt::firstOrFail();

        $this->actingAs($this->user)->post('/remittances', [
            'remittance_date' => now()->toDateString(),
            'summary' => [['payment_mode' => 'Cash', 'amount' => 3000]],
            'total_amount' => 3000,
            'receipts' => [$receipt->id],
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($receipt->fresh()->remittance_id);
    }

    public function test_without_a_holder_it_was_refused(): void
    {
        $cashier = Employee::create([
            'firstname' => 'Tilly', 'lastname' => 'Cashier', 'mobile' => '09170000666',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'user_id' => $this->user->id,
        ]);
        $this->grantRemittanceAccess($this->user);

        $this->postCounterSale()->assertSessionHasNoErrors();
        $receipt = Receipt::firstOrFail();

        // Put it back the way counter sales used to be written, to show this is
        // the condition that produced the refusal rather than something else.
        $receipt->update(['held_by_employee_id' => null]);

        $this->actingAs($this->user)->post('/remittances', [
            'remittance_date' => now()->toDateString(),
            'summary' => [['payment_mode' => 'Cash', 'amount' => 3000]],
            'total_amount' => 3000,
            'receipts' => [$receipt->id],
        ])->assertSessionHasErrors('receipts');

        $this->assertNull($receipt->fresh()->remittance_id);
    }

    /**
     * The way out for receipts already written without a holder: give them one
     * through the handover control, then the cashier can remit them. Checked
     * because this is the advice for the records already on production, which
     * this fix does not reach back and repair.
     */
    public function test_an_unassigned_receipt_can_be_given_a_holder_and_then_remitted(): void
    {
        $cashier = Employee::create([
            'firstname' => 'Tilly', 'lastname' => 'Cashier', 'mobile' => '09170000777',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            'user_id' => $this->user->id,
        ]);
        $this->grantReceiptAccess($this->user);
        $this->grantRemittanceAccess($this->user);

        $this->postCounterSale()->assertSessionHasNoErrors();
        $receipt = Receipt::firstOrFail();
        // Back to how the old records look.
        $receipt->update(['held_by_employee_id' => null]);

        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $cashier->id])
            ->assertOk();

        $this->assertSame($cashier->id, $receipt->fresh()->held_by_employee_id);

        $this->actingAs($this->user)->post('/remittances', [
            'remittance_date' => now()->toDateString(),
            'summary' => [['payment_mode' => 'Cash', 'amount' => 3000]],
            'total_amount' => 3000,
            'receipts' => [$receipt->id],
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($receipt->fresh()->remittance_id);
    }
}
