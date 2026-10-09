<?php

namespace Tests\Feature\Sales;

use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Who may remit what.
 *
 * The rule the business chose: the rep who made the sale answers for it all the
 * way through to the remittance, and you cannot remit cash you are not holding.
 * So a receipt is remittable only when both are true — it is my sale, and the
 * money is in my hands.
 *
 * That makes the two screens differ on purpose. Cash in the Field shows
 * everything a rep is responsible for, wherever it currently sits, because
 * chasing it is their job. Remittance shows the part they are actually holding.
 */
class RemittanceScopeTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        $this->grantReceiptAccess($this->user);
        $this->grantRemittanceAccess($this->user);

        Series::firstOrCreate(['slug' => 'remittance'], [
            'name' => 'Remittance', 'prefix' => 'RM-', 'starting_value' => 1, 'max_digit' => 4,
        ]);
        ListStatus::firstOrCreate(['slug' => 'for-verification'], [
            'name' => 'For Verification', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
    }

    /** The logged-in person, as an employee the system can name. */
    private function me(): Employee
    {
        return Employee::firstOrCreate(
            ['user_id' => $this->user->id],
            [
                'firstname' => 'Me', 'lastname' => 'Rep', 'mobile' => '09170001111',
                'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
            ]
        );
    }

    private function somebodyElse(): Employee
    {
        return Employee::firstOrCreate(
            ['mobile' => '09170002222'],
            [
                'firstname' => 'Other', 'lastname' => 'Rep',
                'birthdate' => '1990-01-01', 'sex' => 'Male', 'religion' => 'None',
            ]
        );
    }

    private function tryRemit(Receipt $receipt)
    {
        return $this->actingAs($this->user)->post('/remittances', [
            'remittance_date' => now()->toDateString(),
            'summary' => [['payment_mode' => 'Cash', 'amount' => (float) $receipt->amount_paid]],
            'total_amount' => (float) $receipt->amount_paid,
            'receipts' => [$receipt->id],
        ]);
    }

    /** A COD order delivered and paid at the door, with the driver carrying it. */
    private function collectedAtTheDoor(?int $repId): Receipt
    {
        $this->postCod(['sales_rep_id' => $repId])->assertSessionHasNoErrors();
        $order = SalesOrder::latest('id')->firstOrFail();

        $this->actingAs($this->user)->put('/sales-orders/'.$order->id, [
            'action' => 'mark-delivered',
            'delivered_at' => now()->toDateString(),
            'collected_amount' => 3000,
            'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        return Receipt::latest('id')->firstOrFail();
    }

    public function test_cash_still_with_the_driver_cannot_be_remitted(): void
    {
        $receipt = $this->collectedAtTheDoor($this->me()->id);

        // My sale, but the driver is holding it.
        $this->assertSame(SalesOrder::firstOrFail()->driver_id, $receipt->held_by_employee_id);
        $this->tryRemit($receipt)->assertSessionHasErrors('receipts');
        $this->assertNull($receipt->fresh()->remittance_id);
    }

    public function test_after_the_handover_the_rep_can_remit_it(): void
    {
        $receipt = $this->collectedAtTheDoor($this->me()->id);

        $this->actingAs($this->user)
            ->putJson('/receipts/'.$receipt->id.'/turn-over', ['held_by_employee_id' => $this->me()->id])
            ->assertOk();

        $this->tryRemit($receipt->fresh())->assertSessionHasNoErrors();
        $this->assertNotNull($receipt->fresh()->remittance_id);
    }

    public function test_holding_somebody_elses_sale_is_not_enough(): void
    {
        $receipt = $this->collectedAtTheDoor($this->somebodyElse()->id);
        // It ends up in my hands, but it is not my sale.
        $receipt->update(['held_by_employee_id' => $this->me()->id]);

        $this->tryRemit($receipt->fresh())->assertSessionHasErrors('receipts');
        $this->assertNull($receipt->fresh()->remittance_id);
    }

    public function test_a_walk_in_i_booked_is_mine_even_with_no_rep(): void
    {
        // The case that produced "You may only remit your own pending
        // receipts." on a counter sale: no rep to own it, so added_by_id has
        // to carry it or it belongs to nobody.
        $this->me();
        $this->postCod([
            'payment_mode' => 'Cash', 'delivery_date' => null, 'driver_id' => null,
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ])->assertSessionHasNoErrors();

        $receipt = Receipt::latest('id')->firstOrFail();

        $this->tryRemit($receipt)->assertSessionHasNoErrors();
        $this->assertNotNull($receipt->fresh()->remittance_id);
    }

    public function test_an_old_receipt_with_no_holder_on_my_sale_still_clears(): void
    {
        // Records written before custody was tracked. Nobody knows where the
        // money is, so refusing them would strand them for good.
        $this->postCod([
            'sales_rep_id' => $this->me()->id,
            'payment_mode' => 'Cash', 'delivery_date' => null, 'driver_id' => null,
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ])->assertSessionHasNoErrors();

        $receipt = Receipt::latest('id')->firstOrFail();
        $receipt->update(['held_by_employee_id' => null]);

        $this->tryRemit($receipt->fresh())->assertSessionHasNoErrors();
        $this->assertNotNull($receipt->fresh()->remittance_id);
    }

    public function test_an_old_receipt_with_no_holder_on_someone_elses_sale_does_not(): void
    {
        $this->me();
        $other = $this->somebodyElse();

        $this->postCod([
            'sales_rep_id' => $other->id,
            'payment_mode' => 'Cash', 'delivery_date' => null, 'driver_id' => null,
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ])->assertSessionHasNoErrors();

        $receipt = Receipt::latest('id')->firstOrFail();
        $receipt->update(['held_by_employee_id' => null]);
        // I typed this order in, which no longer grants ownership: the order
        // has a rep, and the rep owns it.

        $this->tryRemit($receipt->fresh())->assertSessionHasErrors('receipts');
    }
}
