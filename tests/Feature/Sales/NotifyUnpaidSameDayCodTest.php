<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\Customer;
use App\Models\ListStatus;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * The daily nag lists today's orders that are still unpaid. A COD order whose
 * delivery is still days away is not late — the driver has not gone yet — so it
 * belongs on the list only once its delivery day has arrived.
 */
class NotifyUnpaidSameDayCodTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $paymentMode, ?string $deliveryDate): SalesOrder
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'ABC Trading', 'address' => 'Zamboanga City',
            'contact_number' => '09170000000', 'is_active' => 1, 'added_by_id' => $user->id,
        ]);

        $forPayment = ListStatus::firstOrCreate(['slug' => 'for-payment'], [
            'name' => 'For Payment', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
        $unpaid = ListStatus::firstOrCreate(['slug' => 'unpaid'], [
            'name' => 'Unpaid', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        $order = SalesOrder::create([
            'so_number' => 'SO-'.uniqid(),
            'order_date' => today()->toDateString(),
            'customer_id' => $customer->id,
            'status_id' => $forPayment->id,
            'payment_mode' => $paymentMode,
            'delivery_date' => $deliveryDate,
            'due_date' => $deliveryDate,
            'total_amount' => 3000,
            'total_discount' => 0,
            'added_by_id' => $user->id,
        ]);

        ArInvoice::create([
            'sales_order_id' => $order->id,
            'invoice_number' => 'AR-'.uniqid(),
            'invoice_date' => today()->toDateString(),
            'amount_due' => 3000, 'amount_paid' => 0, 'balance_due' => 3000,
            'total_discount' => 0, 'status_id' => $unpaid->id,
        ]);

        return $order;
    }

    private function notifiedOrderNumbers(): array
    {
        $captured = [];

        $this->mock(NotificationService::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('notifyUnpaidSameDaySalesOrders')
                ->andReturnUsing(function ($orders) use (&$captured) {
                    $captured = $orders->pluck('so_number')->all();
                });
        });

        $this->artisan('sales-orders:notify-unpaid-same-day');

        return $captured;
    }

    public function test_a_cod_order_due_for_delivery_today_is_notified(): void
    {
        $order = $this->order('COD', today()->toDateString());

        $this->assertContains($order->so_number, $this->notifiedOrderNumbers());
    }

    public function test_a_cod_order_delivering_later_is_left_alone(): void
    {
        $order = $this->order('COD', today()->addDays(3)->toDateString());

        $this->assertNotContains($order->so_number, $this->notifiedOrderNumbers());
    }

    public function test_an_unpaid_credit_order_is_still_notified(): void
    {
        $order = $this->order('Credit', today()->addDays(30)->toDateString());

        $this->assertContains($order->so_number, $this->notifiedOrderNumbers());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
