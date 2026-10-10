<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * The credit limit binds on edit as well as on creation.
 *
 * It used to be checked only when an order was created, so booking one as COD
 * and then switching it to Credit walked straight past it. Same customer, same
 * amount: refused through the front door, allowed through the side one.
 *
 * Converting COD to Credit is a real decision — the customer cannot pay at the
 * door and the owner lets them have it on terms. It just counts against their
 * limit like any other credit sale.
 */
class CreditLimitOnEditTest extends TestCase
{
    use BuildsCodOrders;
    use RefreshDatabase;

    private Customer $limited;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();

        $this->limited = Customer::create([
            'name' => 'Limited Co', 'address' => 'x', 'contact_number' => '09170000000',
            'credit_limit' => 2000, 'added_by_id' => $this->user->id,
        ]);
    }

    /** Two sacks at 1500 each: 3000, over the 2000 limit. */
    private function bigItems(): array
    {
        return [[
            'product_id' => $this->product->id, 'quantity' => 2, 'price' => 1500,
            'price_type' => 'retail', 'batch_code' => 'BATCH-001', 'discount_per_unit' => 0,
        ]];
    }

    private function edit(SalesOrder $order, array $payload)
    {
        return $this->actingAs($this->user)->put('/sales-orders/'.$order->id, array_merge([
            'id' => $order->id,
            'order_date' => now()->toDateString(),
            'customer_id' => $this->limited->id,
            'delivery_location' => 'Zamboanga City',
        ], $payload));
    }

    public function test_switching_a_cod_order_to_credit_respects_the_limit(): void
    {
        $this->postCod(['customer_id' => $this->limited->id, 'items' => $this->bigItems()])
            ->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->edit($order, [
            'payment_mode' => 'Credit',
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => $this->bigItems(),
        ])->assertSessionHasErrors('credit_limit');

        $this->assertSame('COD', $order->fresh()->payment_mode);
    }

    public function test_switching_within_the_limit_is_allowed(): void
    {
        // One sack at 1500, inside the 2000 limit.
        $small = [[
            'product_id' => $this->product->id, 'quantity' => 1, 'price' => 1500,
            'price_type' => 'retail', 'batch_code' => 'BATCH-001', 'discount_per_unit' => 0,
        ]];

        $this->postCod(['customer_id' => $this->limited->id, 'items' => $small])
            ->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->edit($order, [
            'payment_mode' => 'Credit',
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => $small,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Credit', $order->fresh()->payment_mode);
    }

    public function test_an_edit_is_not_measured_against_its_own_balance(): void
    {
        // A credit order already at the limit. Editing it must not read its own
        // outstanding balance as a second order and refuse a harmless change.
        $small = [[
            'product_id' => $this->product->id, 'quantity' => 1, 'price' => 1500,
            'price_type' => 'retail', 'batch_code' => 'BATCH-001', 'discount_per_unit' => 0,
        ]];

        $this->postCod([
            'customer_id' => $this->limited->id, 'payment_mode' => 'Credit',
            'due_date' => now()->addDays(30)->toDateString(), 'delivery_date' => null,
            'items' => $small,
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::firstOrFail();
        $this->assertSame(1500.0, (float) ArInvoice::firstOrFail()->balance_due);

        $this->edit($order, [
            'payment_mode' => 'Credit',
            'due_date' => now()->addDays(45)->toDateString(),
            'items' => $small,
        ])->assertSessionHasNoErrors();
    }

    public function test_cod_itself_is_never_limited(): void
    {
        // Nothing is extended on a COD order, so the limit does not apply.
        $this->postCod(['customer_id' => $this->limited->id, 'items' => $this->bigItems()])
            ->assertSessionHasNoErrors();

        $this->assertSame('COD', SalesOrder::firstOrFail()->payment_mode);
    }
}
