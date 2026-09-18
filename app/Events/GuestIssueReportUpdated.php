<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Nagbago ang status ng ulat ng isang guest — para sa toast at sa live na
 * listahan sa customer portal.
 *
 * Nasa pribadong `notifications.{userId}` channel (ang tanging channel na
 * pinakikinggan ng customer layout), kaya ang guest lang na may-ari ang
 * nakakatanggap. Ang `message` ay para sa toast lang; ang listahan mismo
 * ay kinukuha muli sa server (GET /my/bookings/{booking}/issues), hindi
 * binabasa mula sa event.
 */
class GuestIssueReportUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public int $userId,
        public int $reportId,
        public int $bookingId,
        public string $status,
        public string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('notifications.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'issue.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'report_id'  => $this->reportId,
            'booking_id' => $this->bookingId,
            'status'     => $this->status,
            'message'    => $this->message,
        ];
    }
}
