<?php

namespace App\Notifications;

use App\Models\Check;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BouncedCheckNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct(public Check $check) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'bounced_check';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'bounced_check',
            'url'  => '/sales/check-monitoring',
            'check_id' => $this->check->id,
            'check_number' => $this->check->check_number,
            'amount' => (float) $this->check->amount,
            'check_date' => $this->check->check_date?->toDateString(),
            'bounce_reason' => $this->check->bounce_reason,
            'customer_name' => $this->check->customer_id
                ? \App\Models\Customer::find($this->check->customer_id)?->name
                : null,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
