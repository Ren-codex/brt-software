<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something that was waiting for approval has been actioned.
 *
 * Sent to every approver of that source so an open bell or approvals page drops
 * the request without waiting for its next poll. It carries no detail on purpose:
 * listeners simply refetch, so there is one source of truth for what is pending.
 *
 * ShouldBroadcastNow for the same reason as the notifications — shared hosting
 * runs no queue worker.
 */
class ApprovalResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param  array<int>  $userIds */
    public function __construct(
        public array $userIds,
        public string $source,
        public int|string $recordId,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(
            fn ($id) => new PrivateChannel('App.Models.User.'.$id),
            $this->userIds
        );
    }

    public function broadcastAs(): string
    {
        return 'approval.resolved';
    }

    public function broadcastWith(): array
    {
        return ['source' => $this->source, 'record_id' => $this->recordId];
    }
}
