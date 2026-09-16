<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
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

    /**
     * Dalawa ang tagapakinig ng iisang pangyayaring ito.
     *
     * Ang `admin-dashboard` ay ang dating gamit — toast at stat counter
     * sa admin panel.
     *
     * Ang `booking-payment.{id}` ay para sa GUEST na nakaupo pa sa
     * checkout o sa "Waiting for Payment" na page. Asynchronous ang QR
     * Ph: ini-scan ng guest ang QR sa ibang device, kaya maaaring hindi
     * kailanman tumakbo ang success callback at ang webhook na lang ang
     * magtatala ng bayad — minuto matapos silang tumingin sa page. Kung
     * walang ipapadalang abiso rito, mananatiling "hinihintay pa" ang
     * nakikita nila gayong bayad na sila.
     *
     * Private ito: pera at balanse ang laman, kaya ang may-ari lang ng
     * booking ang puwedeng makinig (tingnan ang routes/channels.php).
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('admin-dashboard')];

        if ($this->payment->booking_id) {
            $channels[] = new PrivateChannel('booking-payment.'.$this->payment->booking_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'payment.received';
    }

    public function broadcastWith(): array
    {
        $booking = $this->payment->booking;
        return [
            // Ipinapadala ang booking_id at is_refund para makilala ng
            // page ng guest kung ito nga ba ang hinihintay niya. HINDI
            // ito ang pinagbabatayan ng ipinapakitang halaga — nagtatanong
            // pa rin ang page sa payment.status endpoint para sa tunay
            // na estado. Paalala lang ito na may bagong dapat basahin,
            // kaya walang masama kung maantala, madoble o mawala ito.
            'booking_id'     => $this->payment->booking_id,
            'is_refund'      => $this->payment->payment_type === 'refund',
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