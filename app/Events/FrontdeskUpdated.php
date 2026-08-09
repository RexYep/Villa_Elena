<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FrontdeskUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $action,
        public string $message,
        public ?int $bookingId = null,
        public ?int $taskId = null,
        public ?string $actor = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('staff-frontdesk')];
    }

    public function broadcastAs(): string
    {
        return 'frontdesk.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action'     => $this->action,
            'message'    => $this->message,
            'booking_id' => $this->bookingId,
            'task_id'    => $this->taskId,
            'actor'      => $this->actor,
        ];
    }
}
