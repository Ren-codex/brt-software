<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Raised when a record starts waiting for an approver.
 *
 * `url` points at the approvals page filtered to this source rather than at the
 * module screen, so clicking the bell lands somewhere the approver can act
 * immediately. The payload carries the source key and id so the page can scroll
 * to and highlight the exact row.
 *
 * ShouldBroadcastNow, not ShouldBroadcast: shared hosting runs no queue worker,
 * so a queued broadcast would sit in `jobs` and never be delivered.
 */
class ApprovalRequestNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct(
        public string $sourceKey,
        public string $sourceLabel,
        public int|string $recordId,
        public string $reference,
        public ?string $summary = null,
        public ?float $amount = null,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'approval_request';
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'approval_request',
            'url' => '/approvals?source='.$this->sourceKey.'&focus='.$this->recordId,
            'source' => $this->sourceKey,
            'source_label' => $this->sourceLabel,
            'record_id' => $this->recordId,
            'reference' => $this->reference,
            'summary' => $this->summary,
            'amount' => $this->amount,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
