<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnquireSubmittedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $info;
    public $contact;
    public $message;
    /**
     * Create a new event instance.
     */
    public function __construct($info, $contact, $message)
    {
        $this->info = $info;
        $this->contact = $contact;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
