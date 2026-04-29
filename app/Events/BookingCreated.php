<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking->load(['user', 'property']);
    }

    public function broadcastOn(): array
    {
        return [new Channel('admin-dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'booking.created';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_ref'   => $this->booking->booking_ref,
            'guest'         => $this->booking->user->full_name ?? 'Guest',
            'property'      => $this->booking->property->property_name ?? 'N/A',
            'check_in'      => $this->booking->check_in_date->format('M d, Y'),
            'check_out'     => $this->booking->check_out_date->format('M d, Y'),
            'total_amount'  => $this->booking->total_amount,
            'status'        => $this->booking->status,
            'booking_id'    => $this->booking->id,
            'created_at'    => now()->format('h:i A'),
        ];
    }
}