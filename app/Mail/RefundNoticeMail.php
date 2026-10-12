<?php

namespace App\Mail;

use App\Models\Booking;
use App\Support\GuestEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Ang email na katapat ng isang in-app na abiso tungkol sa refund (v7.68).
//
// Hanggang ngayon ay in-app lang ang lahat ng abiso sa refund. Ang guest na
// hindi nagla-log in — na siyang karaniwang kalagayan matapos kanselahin ng
// resort ang booking niya — ay hindi kailanman nalalaman na may ibabalik sa
// kanya, o na hinihintay namin ang account niya para maipadala ito.
//
// Iisang mailable para sa lahat ng yugto. Ang pamagat at ang pangungusap ay
// ibinibigay ng tumatawag (`NotificationHelper`), kaya PAREHO ang sinasabi
// ng email at ng abiso sa kampana — walang pangalawang kopya ng pananalita
// rito. Ang tanging sariling salita ng email ay ang nasa button.
class RefundNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        // Ang pamagat ng abiso, hal. "Refund Coming".
        public string $heading,
        public string $body,
        public float $amount,
        public string $actionLabel,
        // ABSOLUTE — hindi gaya ng `link` ng in-app na abiso, na relative.
        // Walang pinagbabatayang host ang isang email client.
        public string $actionUrl,
        // Isang pangungusap sa itaas ng button kapag may kailangang gawin
        // ang guest (ibigay o itama ang account). NULL kung wala.
        public ?string $actionNote = null,
    ) {}

    public function build()
    {
        // Walang pangalan ng resort sa subject: nasa "From" na iyon, katabi
        // mismo ng subject sa inbox. Ang `heading` ay siya ring headline
        // ng email (emails/layout).
        return $this
            ->from(GuestEmail::sender())
            ->subject("{$this->heading} — {$this->booking->booking_ref}")
            ->view('emails.refund_notice')
            ->text('emails.refund_notice_text')
            ->with(['preheader' => '₱'.number_format($this->amount, 2)." for booking {$this->booking->booking_ref}."]);
    }
}
