<?php

namespace App\Events;

use App\Models\Property;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Property $property;
    public string $oldStatus;

    public function __construct(Property $property, string $oldStatus)
    {
        $this->property  = $property;
        $this->oldStatus = $oldStatus;
    }

    public function broadcastOn(): array
    {
        return [new Channel('admin-dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'property.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'property_id'   => $this->property->id,
            'property_name' => $this->property->property_name,
            'old_status'    => $this->oldStatus,
            'new_status'    => $this->property->status,
            'updated_at'    => now()->format('h:i A'),
        ];
    }
}