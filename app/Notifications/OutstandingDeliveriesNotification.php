<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * The end-of-day nudge: goods delivered with nothing collected, and money
 * collected but still in somebody's hands. Sent to the office as a whole and
 * to each rep for their own orders — `scope` says which.
 */
class OutstandingDeliveriesNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public int $uncollectedCount,
        public float $uncollectedAmount,
        public int $heldCount,
        public float $heldAmount,
        public int $days,
        public string $scope = 'all',
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'outstanding_deliveries';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'outstanding_deliveries',
            'scope' => $this->scope,
            'days' => $this->days,
            'uncollected_count' => $this->uncollectedCount,
            'uncollected_amount' => $this->uncollectedAmount,
            'held_count' => $this->heldCount,
            'held_amount' => $this->heldAmount,
            'message' => $this->summary(),
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    /** One line somebody can act on without opening anything. */
    public function summary(): string
    {
        $parts = [];

        if ($this->uncollectedCount > 0) {
            $parts[] = $this->uncollectedCount.' '.($this->uncollectedCount === 1 ? 'delivery' : 'deliveries')
                .' uncollected ('.$this->peso($this->uncollectedAmount).')';
        }

        if ($this->heldCount > 0) {
            $parts[] = $this->peso($this->heldAmount).' still in the field';
        }

        return implode(', ', $parts).', '.$this->days.'+ days.';
    }

    private function peso(float $amount): string
    {
        return '₱'.number_format($amount, 2);
    }
}
