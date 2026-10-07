<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * What "unconfirmed" means on the Deliveries list and on Cash in the Field.
 *
 * Both screens used to flag any unconfirmed check or transfer by matching the
 * payment mode alone. A transfer only waits on the bank when the order is COD —
 * on a credit sale it settles the moment it is recorded — so a credit sale's
 * transfer was marked unconfirmed permanently, which reads as money stuck when
 * nothing is. A check waits whatever the order was.
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

        return [
            'board' => $board['with_driver'][0]['confirmed'] ?? null,
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

    public function test_a_cod_transfer_is_flagged_as_waiting(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $this->pay('Bank Transfer');

        $this->assertSame(['board' => false, 'field' => false], $this->flags());
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
