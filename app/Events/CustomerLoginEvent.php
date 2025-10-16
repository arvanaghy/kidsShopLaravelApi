<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerLoginEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $phone;
    public $smsCode;
    /**
     * Create a new event instance.
     */
    public function __construct($phone, $smsCode)
    {
        $this->phone = $phone;
        $this->smsCode = $smsCode;
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
