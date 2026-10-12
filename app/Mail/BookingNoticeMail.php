<?php

namespace App\Mail;

use App\Models\Booking;
use App\Support\GuestEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The email beside an in-app notice that a booking changed without a
 * payment: an unpaid hold ran out, the booking was cancelled, or it was
 * moved to another date (v7.74).
 *
 * One mailable for all three, as RefundNoticeMail is for refunds. The
 * headline and the sentence are given by the caller (`NotificationHelper`),
 * so the email and the bell say the same thing; there is no second copy of
 * the wording here. The rows are the booking's dates, built by
 * `BookingMailHelper`.
 */
class BookingNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{0: string, 1: string, 2?: bool}>  $rows  label, value, and `true` for an amount still due
     */
    public function __construct(
        public Booking $booking,
        // The notice's title, e.g. "Booking Cancelled".
        public string $heading,
        public string $body,
        public array $rows,
        // ABSOLUTE, unlike the in-app notice's `link`: an email client has no
        // host to resolve a relative one against.
        public string $actionUrl,
        // One more sentence, printed in the highlighted box. NULL for none.
        public ?string $note = null,
    ) {}

    public function build()
    {
        // No resort name in the subject: it is the sender, right beside it.
        return $this
            ->from(GuestEmail::sender())
            ->subject("{$this->heading} — {$this->booking->booking_ref}")
            ->view('emails.booking_notice')
            ->text('emails.booking_notice_text')
            ->with(['preheader' => $this->body]);
    }
}
