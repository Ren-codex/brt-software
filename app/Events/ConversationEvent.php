<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationEvent implements ShouldBroadcast 
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message,$channel;

    public function __construct($message, $channel)
    {
        $this->message = $message;
        $this->channel = $channel;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel($this->channel),
        ];
    }
}


// 'message' => $this->message,
// 'sender_id' => $this->sender_id,
// 'name' => $this->sender->profile->firstname.' '.$this->sender->profile->lastname,
// 'avatar' => $this->sender->profile->avatar,
// 'time' => $this->created_at