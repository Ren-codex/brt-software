<?php

namespace Tests\Feature\Sales;

use App\Models\ListStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Sales\Concerns\BuildsCodOrders;
use Tests\TestCase;

/**
 * An order's status tracks money and returns; nothing said whether the goods
 * ever arrived. The office records that when the driver reports back, and the
 * stamp is what separates "not delivered yet" from "delivered, money still out".
 */
class MarkDeliveredTest extends TestCase
{
    use RefreshDatabase;
    use BuildsCodOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCodFixture();
    }

    private function markDelivered(SalesOrder $order, array $payload = [])
    {
        return $this->actingAs($this->user)
            ->put('/sales-orders/'.$order->id, array_merge(['action' => 'mark-delivered'], $payload));
    }

    public function test_marking_an_order_delivered_stamps_when_and_who(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->markDelivered($order)->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertNotNull($order->delivered_at);
        $this->assertSame($this->user->id, $order->delivered_by_id);
    }

    public function test_a_second_mark_keeps_the_first_timestamp(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();

        $this->markDelivered($order)->assertSessionHasNoErrors();
        $first = $order->fresh()->delivered_at;

        $this->travel(2)->days();
        $this->markDelivered($order)->assertSessionHasNoErrors();

        $this->assertEquals($first, $order->fresh()->delivered_at);
    }

    public function test_the_list_carries_the_delivery_for_the_screen(): void
    {
        // The row badge and the modal read these; a value the resource never
        // sends is a blank badge no compile check would catch.
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $this->markDelivered($order)->assertSessionHasNoErrors();

        $response = $this->actingAs($this->user)->getJson('/sales-orders?option=lists&count=10');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $order->id);
        $this->assertNotNull($row['delivered_at']);
        $this->assertNotNull($row['delivered_by']);
        // The modal names each line; products have no name column of their own,
        // so it is built from brand, weight and unit.
        $this->assertSame('Test Brand 25 Sack', $row['items'][0]['product_name']);
    }

    public function test_a_cancelled_order_cannot_be_marked_delivered(): void
    {
        $this->postCod()->assertSessionHasNoErrors();
        $order = SalesOrder::firstOrFail();
        $order->update(['status_id' => ListStatus::where('slug', 'cancelled')->value('id')]);

        $this->markDelivered($order)->assertSessionHasErrors('delivered_at');

        $this->assertNull($order->fresh()->delivered_at);
    }
}
