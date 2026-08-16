<?php

namespace App\Notifications;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UnpaidSameDaySalesOrderNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(public SalesOrder $salesOrder, public float $balanceDue) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'unpaid_same_day_sales_order';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'            => 'unpaid_same_day_sales_order',
            'sales_order_id'  => $this->salesOrder->id,
            'so_number'       => $this->salesOrder->so_number,
            'customer_name'   => $this->salesOrder->customer->name,
            'payment_mode'    => $this->salesOrder->payment_mode,
            'balance_due'     => $this->balanceDue,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
