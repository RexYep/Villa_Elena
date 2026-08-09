<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Lets the public property page (portal/property.blade.php) update its
// availability calendar live instead of only reflecting bookings that
// existed when the page was first loaded.
class PropertyAvailabilityChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $propertyId,
        public string $action, // 'blocked' | 'freed'
        public string $checkIn,
        public string $checkOut,
        public ?string $checkInTime = null,
        public ?string $checkOutTime = null,
        public ?int $bookingId = null,
    ) {}

    public function broadcastOn(): array
    {
        // Public and per-property — this mirrors exactly what's already
        // server-rendered into every visitor's page on load ($bookedRanges
        // in PortalController::propertyDetail), so there's no new privacy
        // exposure: just the blocked date range, no guest name or amount.
        return [new Channel('property-availability.'.$this->propertyId)];
    }

    public function broadcastAs(): string
    {
        return 'availability.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action'         => $this->action,
            'check_in'       => $this->checkIn,
            'check_out'      => $this->checkOut,
            'check_in_time'  => $this->checkInTime,
            'check_out_time' => $this->checkOutTime,
            'booking_id'     => $this->bookingId,
        ];
    }
}
