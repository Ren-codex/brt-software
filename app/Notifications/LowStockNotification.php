<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public Product $product,
        public int $currentStock
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'low_stock';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'          => 'low_stock',
            'product_id'    => $this->product->id,
            'product_name'  => $this->product->brand->name . ' ' . $this->product->pack_size . ' ' . $this->product->unit->name,
            'current_stock' => $this->currentStock,
            'minimum_stock' => $this->product->minimum_stock,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
