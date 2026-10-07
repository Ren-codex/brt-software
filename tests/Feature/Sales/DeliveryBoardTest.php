<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Employee;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * One board for the life of a delivery, so nobody has to visit four screens to
 * find what needs chasing. Each column is a question: whose goods are still
 * out, who owes for goods they have, and who is carrying money.
 */
class DeliveryBoardTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
        $this->grantArInvoiceAccess($this->user);
        ListStatus::firstOrCreate(['slug' => 'for-release'], [
            'name' => 'For Release', 'text_color' => '#fff', 'bg_color' => '#007678',
        ]);
    }

    private function board(): array
    {
        return $this->actingAs($this->user)
            ->getJson('/sales-orders?option=delivery-board')
            ->json();
    }

    private function markDelivered(SalesOrder $order, array $payload = [])
    {
        return $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, array_merge(['action' => 'mark-delivered'], $payload));
    }

    public function test_an_undelivered_order_waits_in_the_first_column(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $board = $this->board();

        $this->assertCount(1, $board['out_for_delivery']);
        $this->assertSame($order->so_number, $board['out_for_delivery'][0]['reference']);
        $this->assertCount(0, $board['to_collect']);
    }

    public function test_once_delivered_it_moves_to_the_collecting_column(): void
    {
        $this->postCredit()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->markDelivered($order)->assertSessionHasNoErrors();

        $board = $this->board();
        $this->assertCount(0, $board['out_for_delivery']);
        $this->assertCount(1, $board['to_collect']);
        $this->assertEquals(3000, $board['to_collect'][0]['amount'], 'What is still owed, not the order total.');
    }

    public function test_once_collected_it_moves_to_the_money_column(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::with('items')->firstOrFail();

        $this->markDelivered($order, [
            'accepted_quantities' => [$order->items->first()->id => 2],
            'collected_amount' => 3000,
            'collected_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        $board = $this->board();
        $this->assertCount(0, $board['to_collect']);
        $this->assertCount(1, $board['with_driver']);
        $this->assertEquals(3000, $board['with_driver'][0]['amount']);
    }

    public function test_a_counter_sale_never_appears_on_the_board(): void
    {
        // Nobody delivers it: the customer carries it out as they pay.
        $this->actingAs($this->user)->post('/sales-orders', $this->payload([
            'payment_mode' => 'Cash',
            'delivery_date' => null,
            'driver_id' => null,
            'payment_lines' => [['payment_mode' => 'Cash', 'payment_amount' => 3000]],
        ]))->assertSessionHasNoErrors();

        $board = $this->board();

        $this->assertCount(0, $board['out_for_delivery']);
        $this->assertCount(0, $board['to_collect']);
    }

    public function test_a_cancelled_order_leaves_the_board(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        SalesOrder::firstOrFail()->update([
            'status_id' => ListStatus::where('slug', 'cancelled')->value('id'),
        ]);

        $this->assertCount(0, $this->board()['out_for_delivery']);
    }

    public function test_it_names_the_driver_carrying_each_one(): void
    {
        $driver = Employee::create([
            'firstname' => 'Ana', 'lastname' => 'Cruz', 'mobile' => '09170000000',
            'birthdate' => '1990-01-01', 'sex' => 'Female', 'religion' => 'None',
        ]);

        $this->postCod(['driver_id' => $driver->id])->assertSessionHasNoErrors();

        $this->assertSame($driver->fullname, $this->board()['out_for_delivery'][0]['person']);
    }
}
