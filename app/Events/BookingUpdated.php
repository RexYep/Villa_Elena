<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Fired whenever an existing booking changes in a way other admins
// viewing the dashboard/calendar right now should know about — a
// status transition (pending -> confirmed, cancelled, etc.) or a
// calendar drag-and-drop date move. Kept separate from BookingCreated,
// which only covers brand-new bookings.
class BookingUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $action, // 'status_changed' | 'moved'
        public ?string $oldStatus = null,
        public ?string $newStatus = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('admin-dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'booking.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id'  => $this->booking->id,
            'booking_ref' => $this->booking->booking_ref,
            'action'      => $this->action,
            'old_status'  => $this->oldStatus,
            'new_status'  => $this->newStatus,
        ];
    }
}
