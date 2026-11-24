<?php

namespace Tests\Feature\Checks;

use App\Models\ArInvoice;
use App\Models\Check;
use App\Models\Receipt;
use App\Services\Modules\ArInvoiceClass;
use App\Services\Modules\CheckRegisterClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Cancelling a pending cheque withdraws it — the customer is paying another way
 * (cash, or carrying it as credit). The cheque stops holding the invoice, so the
 * amount is owing and collectable again; nothing was ever posted, so the balance
 * never dropped. It is not a bounce: no bank failure, no rep chase.
 */
class CancelCheckTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
    }

    private function creditInvoice(): ArInvoice
    {
        $this->actingAs($this->user)->post('/sales-orders', $this->payload([
            'payment_mode' => 'Credit Sales',
            'due_date' => now()->addDays(30)->toDateString(),
        ]))->assertSessionHasNoErrors();

        return ArInvoice::firstOrFail();
    }

    private function payByCheck(ArInvoice $invoice, float $amount = 3000)
    {
        return $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'action' => 'payment',
            'payment_date' => now()->toDateString(),
            'balance_due' => (float) $invoice->fresh()->balance_due,
            'splits' => [[
                'payment_mode' => 'Check',
                'amount' => $amount,
                'bank_account_id' => null,
                'reference_number' => 'CHK-'.uniqid(),
                'check_date' => now()->addDays(10)->toDateString(),
            ]],
        ]);
    }

    private function register(): CheckRegisterClass
    {
        return app(CheckRegisterClass::class);
    }

    private function theCheque(): Check
    {
        return Check::where('source_type', Receipt::class)->firstOrFail();
    }

    public function test_cancelling_releases_the_invoice_so_it_can_be_paid_another_way(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();

        // While pending the cheque holds the full amount against the invoice.
        $this->assertEquals(3000, app(ArInvoiceClass::class)->unclearedTotal($invoice->fresh()));

        $this->actingAs($this->user);
        $cheque = $this->register()->markCancelled($this->theCheque(), 'Customer paying cash instead');

        $this->assertSame(Check::STATUS_CANCELLED, $cheque->fresh()->status);
        $this->assertSame('Customer paying cash instead', $cheque->fresh()->cancel_reason);
        $this->assertNotNull($cheque->fresh()->cancelled_at);

        // The hold is gone and the balance never dropped: fully owing & collectable.
        $this->assertEquals(0, app(ArInvoiceClass::class)->unclearedTotal($invoice->fresh()));
        $this->assertEquals(3000, $invoice->fresh()->balance_due);

        // And it really can be collected now — a cash payment goes through.
        $this->actingAs($this->user)->put('/ar-invoices/'.$invoice->id, [
            'id' => $invoice->id,
            'option' => 'payment',
            'action' => 'payment',
            'payment_date' => now()->toDateString(),
            'balance_due' => (float) $invoice->fresh()->balance_due,
            'splits' => [['payment_mode' => 'Cash', 'amount' => 3000]],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0, $invoice->fresh()->balance_due);
    }

    public function test_only_a_pending_cheque_can_be_cancelled(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();

        $this->theCheque()->update(['status' => Check::STATUS_BOUNCED]);

        $this->actingAs($this->user);
        $this->expectException(ValidationException::class);
        $this->register()->markCancelled($this->theCheque(), 'too late');
    }

    public function test_a_reason_is_required(): void
    {
        $invoice = $this->creditInvoice();
        $this->payByCheck($invoice)->assertSessionHasNoErrors();

        $this->actingAs($this->user);
        $this->expectException(ValidationException::class);
        $this->register()->markCancelled($this->theCheque(), '   ');
    }
}
