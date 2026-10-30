<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * What "unconfirmed" means on the Deliveries list and on Cash in the Field.
 *
 * Only a cheque waits on the bank now: it can bounce, so its amount is not real
 * until it clears. A bank transfer settles the moment it is recorded, whether
 * the order is COD or credit — it flows like cash and is accounted for through
 * remittance, not flagged as money stuck at the bank. Cash was never flagged.
 */
class UnconfirmedFlagTest extends TestCase
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
    }

    private function pay(string $mode, array $extra = []): void
    {
        $invoice = ArInvoice::firstOrFail();

        // A transfer must name the bank it reached; harmless on other modes.
        if ($mode === 'Bank Transfer' && ! array_key_exists('bank_account_id', $extra)) {
            $extra['bank_account_id'] = $this->fixtureBank()->id;
        }

        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, array_merge([
            'id' => $invoice->id,
            'option' => 'payment',
            'balance_due' => (float) $invoice->balance_due,
            'amount_paid' => (float) $invoice->balance_due,
            'payment_date' => now()->toDateString(),
            'payment_mode' => $mode,
            'reference_number' => 'REF-1',
        ], $extra))->assertSessionHasNoErrors();
    }

    /** The 'confirmed' flag each screen reports for the only receipt present. */
    private function flags(): array
    {
        $board = $this->actingAs($this->user)
            ->getJson('/sales-orders?option=delivery-board')->assertOk()->json();
        $field = $this->actingAs($this->user)
            ->getJson('/remittances?option=field-collections')->assertOk()->json();

        // A collection sits under "With driver / checker" while somebody is
        // carrying it, and under "Waiting for the bank" once the paper is in
        // and only the bank is holding it up. The flag means the same thing in
        // either place, which is what this test is about.
        $row = ($board['with_driver'][0] ?? null) ?? ($board['awaiting_bank'][0] ?? null);

        return [
            'board' => $row['confirmed'] ?? null,
            'field' => $field[0]['confirmed'] ?? null,
        ];
    }

    public function test_a_credit_sales_transfer_is_not_flagged_as_waiting(): void
    {
        $this->postCod([
            'payment_mode' => 'Credit',
            'due_date' => now()->toDateString(),
            'delivery_date' => null,
        ])->assertSessionHasNoErrors();

        $this->pay('Bank Transfer');

        // It settled on the spot, so there is nothing for the bank to confirm.
        $this->assertSame(['board' => true, 'field' => true], $this->flags());
    }

    public function test_a_cod_transfer_is_not_flagged_as_waiting(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->pay('Bank Transfer');

        // It settles like cash and is carried to remittance, not held at the
        // bank, so nothing flags it as waiting.
        $this->assertSame(['board' => true, 'field' => true], $this->flags());
    }

    public function test_a_check_is_flagged_whatever_the_order_was(): void
    {
        $this->postCod([
            'payment_mode' => 'Credit',
            'due_date' => now()->toDateString(),
            'delivery_date' => null,
        ])->assertSessionHasNoErrors();

        $this->pay('Check', ['check_date' => now()->addDays(7)->toDateString()]);

        $this->assertSame(['board' => false, 'field' => false], $this->flags());
    }

    public function test_cash_is_never_flagged(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->pay('Cash');

        $this->assertSame(['board' => true, 'field' => true], $this->flags());
    }
}
