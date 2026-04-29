<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment->load(['booking.user', 'booking.property']);
    }

    public function broadcastOn(): array
    {
        return [new Channel('admin-dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'payment.received';
    }

    public function broadcastWith(): array
    {
        $booking = $this->payment->booking;
        return [
            'amount'         => $this->payment->amount,
            'payment_method' => $this->payment->payment_method,
            'payment_type'   => $this->payment->payment_type,
            'booking_ref'    => $booking->booking_ref ?? 'N/A',
            'guest'          => $booking->user->full_name ?? 'Guest',
            'property'       => $booking->property->property_name ?? 'N/A',
            'created_at'     => now()->format('h:i A'),
        ];
    }
}