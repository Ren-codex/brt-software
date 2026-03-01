<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactReadEvent implements ShouldBroadcast 
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $contactId;
    public $isRead;

    public function __construct($contactId, $isRead)
    {
        $this->contactId = $contactId;
        $this->isRead = $isRead;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('contacts'),
        ];
    }
}
