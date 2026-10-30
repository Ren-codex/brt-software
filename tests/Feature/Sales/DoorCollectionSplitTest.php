<?php

namespace Tests\Feature\Sales;

use App\Models\BankAccount;
use App\Models\Receipt;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * Money handed over at the door is not always one kind: part cash and part
 * transfer is ordinary, and a transfer names the account it went to.
 */
class DoorCollectionSplitTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
    }

    private function deliver(SalesOrder $order, array $payload)
    {
        return $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, array_merge(['action' => 'mark-delivered'], $payload));
    }

    public function test_a_cod_order_can_be_paid_two_ways_at_the_door(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $bank = BankAccount::first() ?? BankAccount::create(['bank_name' => 'Test Bank', 'account_name' => 'BRT', 'gl_code' => '1029', 'is_active' => true]);

        $this->deliver($order, ['collected_splits' => [
            ['payment_mode' => 'Cash', 'amount' => 1000],
            ['payment_mode' => 'Bank Transfer', 'amount' => 2000, 'bank_account_id' => $bank->id, 'reference_number' => 'TRN-1'],
        ]])->assertSessionHasNoErrors();

        $this->assertNotNull($order->fresh()->delivered_at);

        $receipts = Receipt::where('receipt_type', 'payment')->orderBy('id')->get();
        $this->assertCount(2, $receipts);
        $this->assertEquals([1000.0, 2000.0], $receipts->map(fn ($r) => (float) $r->amount_paid)->all());
        $this->assertSame($bank->id, $receipts[1]->bank_account_id);
        $this->assertSame('TRN-1', $receipts[1]->reference_number);
    }

    public function test_the_lines_together_still_have_to_cover_a_cod_order(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->deliver($order, ['collected_splits' => [
            ['payment_mode' => 'Cash', 'amount' => 1000],
            ['payment_mode' => 'Cash', 'amount' => 500],
        ]])->assertSessionHasErrors('collected_amount');

        $this->assertNull($order->fresh()->delivered_at);
        $this->assertSame(0, Receipt::where('receipt_type', 'payment')->count());
    }
}
