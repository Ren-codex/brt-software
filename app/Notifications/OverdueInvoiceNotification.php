<?php

namespace App\Notifications;

use App\Models\ArInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class OverdueInvoiceNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(public ArInvoice $invoice) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'overdue_invoice';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'           => 'overdue_invoice',
            'invoice_id'     => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'customer_name'  => $this->invoice->sales_order->customer->name,
            'balance_due'    => (float) $this->invoice->balance_due,
            'due_date'       => $this->invoice->due_date->toDateString(),
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
