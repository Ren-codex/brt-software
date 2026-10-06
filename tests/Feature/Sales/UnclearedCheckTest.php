<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Check;
use App\Models\Receipt;
use App\Models\SalesOrder;
use App\Services\Modules\ArInvoiceClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * A post-dated check is a promise, so the invoice keeps its full balance until
 * the bank says otherwise. That honesty about the money is also a trap: the
 * balance reads as collectable when part of it is already spoken for, and
 * collecting against it twice credits the customer twice — once in cash now,
 * once when the check clears.
 */
class UnclearedCheckTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
    }

    /** A credit order, so a partial payment is allowed and the maths stays visible. */
    private function creditInvoice(): ArInvoice
    {
        $this->actingAs($this->user)->post('/sales-orders', $this->payload([
            'payment_mode' => 'Credit Sales',
            'due_date' => now()->addDays(30)->toDateString(),
        ]))->assertSessionHasNoErrors();

        return ArInvoice::firstOrFail();
    }

    private function pay(ArInvoice $invoice, array $split)
    {
        return $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'action' => 'payment',
            'payment_date' => now()->toDateString(),
            'balance_due' => (float) $invoice->fresh()->balance_due,
            'splits' => [$split],
        ]);
    }

    private function payByCheck(ArInvoice $invoice, float $amount = 3000, ?string $checkDate = null)
    {
        return $this->pay($invoice, [
            'payment_mode' => 'Check',
            'amount' => $amount,
            'bank_account_id' => null,
            'reference_number' => 'CHK-'.uniqid(),
            'check_date' => $checkDate ?? now()->addDays(10)->toDateString(),
        ]);
    }

    public function test_a_post_dated_check_leaves_the_invoice_unpaid(): void
    {
        $invoice = $this->creditInvoice();

        $this->payByCheck($invoice)->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertEquals(3000, $invoice->balance_due, 'A check is not money until it clears.');
        $this->assertEquals(0, $invoice->amount_paid);
        $this->assertSame('Unpaid', $invoice->status->name);
    }

    public function test_the_held_check_is_counted_against_the_invoice(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();

        $this->assertEquals(3000, app(ArInvoiceClass::class)->unclearedTotal($invoice->fresh()));
    }

    public function test_a_second_payment_for_money_already_promised_is_refused(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();

        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 3000])
            ->assertSessionHasErrors('amount_paid');

        $invoice->refresh();
        $this->assertEquals(3000, $invoice->balance_due, 'Nothing was applied.');
        $this->assertEquals(0, $invoice->amount_paid);
    }

    public function test_the_part_the_check_does_not_cover_can_still_be_collected(): void
    {
        // Half promised by check, half still genuinely owed in cash.
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice, 1000)->assertSessionHasNoErrors();

        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 2000])
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertEquals(1000, $invoice->balance_due);
        $this->assertEquals(2000, $invoice->amount_paid);
    }

    public function test_a_bounced_check_holds_nothing_against_the_invoice(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();

        Check::where('source_type', Receipt::class)->firstOrFail()->update([
            'status' => Check::STATUS_BOUNCED,
            'bounced_at' => now(),
        ]);

        // The money never arrived, so the customer still owes it in full.
        $this->assertEquals(0, app(ArInvoiceClass::class)->unclearedTotal($invoice->fresh()));
        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 3000])
            ->assertSessionHasNoErrors();

        $this->assertEquals(0, $invoice->fresh()->balance_due);
    }

    public function test_confirming_a_check_settles_the_invoice(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();
        $receipt = Receipt::where('ar_invoice_id', $invoice->id)->firstOrFail();

        app(ArInvoiceClass::class)->confirmReceipt($receipt->id, 'BPI');

        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance_due);
        $this->assertEquals(3000, $invoice->amount_paid);
        $this->assertSame('Paid', $invoice->status->name);
    }

    public function test_a_check_cannot_be_confirmed_onto_an_invoice_already_settled(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();
        $receipt = Receipt::where('ar_invoice_id', $invoice->id)->firstOrFail();

        // However the invoice came to be settled — a correction, a return, a
        // payment taken elsewhere — the check is no longer a payment.
        $invoice->update(['amount_paid' => 3000, 'balance_due' => 0]);

        try {
            app(ArInvoiceClass::class)->confirmReceipt($receipt->id, 'BPI');
            $this->fail('Confirming onto a settled invoice should be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already been paid in full', $e->getMessage());
        }

        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance_due, 'The balance was not driven negative.');
        $this->assertEquals(3000, $invoice->amount_paid, 'The customer was not credited twice.');
        $this->assertNull($receipt->fresh()->confirmed_at);
    }

    public function test_confirming_more_than_is_outstanding_is_refused(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();
        $receipt = Receipt::where('ar_invoice_id', $invoice->id)->firstOrFail();

        $invoice->update(['amount_paid' => 2000, 'balance_due' => 1000]);

        try {
            app(ArInvoiceClass::class)->confirmReceipt($receipt->id, 'BPI');
            $this->fail('A check larger than the balance should be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('still outstanding', $e->getMessage());
        }

        $this->assertEquals(1000, $invoice->fresh()->balance_due);
    }

    public function test_the_invoice_row_says_a_check_is_being_held(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();

        $row = collect($this->actingAs($this->user)
            ->getJson('/ar-invoices?option=lists')
            ->json('data'))
            ->firstWhere('id', $invoice->id);

        $this->assertNotNull($row['uncleared'], 'Nothing on the row would warn before a second payment.');
        $this->assertEquals(3000, $row['uncleared']['amount']);
        $this->assertSame('Check', $row['uncleared']['mode']);
    }

    public function test_the_payment_screen_says_how_much_is_left_to_collect(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice, 1000)->assertSessionHasNoErrors();

        $balance = $this->actingAs($this->user)
            ->getJson('/ar-invoices?option=balance&id='.$invoice->id)
            ->json();

        $this->assertEquals(3000, $balance['balance_due'], 'Still owed, honestly.');
        $this->assertEquals(1000, $balance['uncleared']['amount']);
        $this->assertEquals(2000, $balance['collectable'], 'What can be taken again today.');
    }

    public function test_an_invoice_paid_by_instalments_settles_once_every_check_clears(): void
    {
        // Part cash now, the rest promised by two checks dated weeks apart.
        $invoice = $this->creditInvoice();
        $service = app(ArInvoiceClass::class);

        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 1000])->assertSessionHasNoErrors();
        $this->payByCheck($invoice, 1000, now()->addDays(10)->toDateString())->assertSessionHasNoErrors();
        $this->payByCheck($invoice, 1000, now()->addDays(20)->toDateString())->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertEquals(2000, $invoice->balance_due, 'Only the cash was recognised.');
        $this->assertEquals(1000, $invoice->amount_paid);
        $this->assertEquals(2000, $service->unclearedTotal($invoice), 'Both checks are counted.');

        // Nothing is left to collect: the rest is promised, not missing.
        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 500])
            ->assertSessionHasErrors('amount_paid');

        $checks = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('payment_mode', 'Check')->orderBy('id')->get();

        $service->confirmReceipt($checks[0]->id, 'BPI');
        $invoice->refresh();
        $this->assertEquals(1000, $invoice->balance_due, 'One cleared, one still outstanding.');
        $this->assertEquals(1000, $service->unclearedTotal($invoice));

        $service->confirmReceipt($checks[1]->id, 'BDO');
        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance_due);
        $this->assertEquals(3000, $invoice->amount_paid, 'Paid once over, not twice.');
        $this->assertSame('Paid', $invoice->status->name);
    }

    public function test_a_bounced_instalment_can_be_replaced_without_disturbing_the_rest(): void
    {
        $invoice = $this->creditInvoice();
        $service = app(ArInvoiceClass::class);

        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 1000])->assertSessionHasNoErrors();
        $this->payByCheck($invoice, 1000, now()->addDays(10)->toDateString())->assertSessionHasNoErrors();
        $this->payByCheck($invoice, 1000, now()->addDays(20)->toDateString())->assertSessionHasNoErrors();

        $checks = Receipt::where('ar_invoice_id', $invoice->id)
            ->where('payment_mode', 'Check')->orderBy('id')->get();

        Check::where('source_type', Receipt::class)->where('source_id', $checks[0]->id)
            ->update(['status' => Check::STATUS_BOUNCED, 'bounced_at' => now()]);

        // The bounced one frees its share, and only its share.
        $this->assertEquals(1000, $service->unclearedTotal($invoice->fresh()));
        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 1000])->assertSessionHasNoErrors();

        $service->confirmReceipt($checks[1]->id, 'BDO');
        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance_due);
        $this->assertEquals(3000, $invoice->amount_paid);
    }

    public function test_the_refusal_counts_the_checks_rather_than_naming_one(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice, 1000, now()->addDays(10)->toDateString())->assertSessionHasNoErrors();
        $this->payByCheck($invoice, 1000, now()->addDays(20)->toDateString())->assertSessionHasNoErrors();

        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 1500])
            ->assertSessionHasErrors(['amount_paid' => '₱2,000.00 of this invoice is already covered by 2 payments that have not cleared, so only ₱1,000.00 can be collected again. Confirm or bounce them first.']);
    }

    public function test_an_ordinary_cash_payment_is_untouched(): void
    {
        $invoice = $this->creditInvoice();

        $this->pay($invoice, ['payment_mode' => 'Cash', 'amount' => 3000])
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance_due);
        $this->assertSame('Paid', $invoice->status->name);
        // Paid but still to be delivered, so the order waits at For Release.
        $this->assertSame('For Release', SalesOrder::firstOrFail()->status->name);
    }
}
