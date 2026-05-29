<?php

namespace App\Notifications;

use App\Models\PettyCashFund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LowBalanceFundNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(public PettyCashFund $fund) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'      => 'low_balance',
            'fund_id'   => $this->fund->id,
            'fund_name' => $this->fund->name,
            'balance'   => (float) $this->fund->balance,
            'threshold' => (float) $this->fund->low_balance_threshold,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
