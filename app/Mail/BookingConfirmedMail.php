<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Ipinapadala sa BAWAT matagumpay na bayad, hindi lang sa una. Dati,
// naka-gate ito sa $wasPending ng confirmOnFirstPayment(), kaya ang
// guest na nagbayad ng 50% downpayment ay nakakatanggap ng email, pero
// ang pagbabayad ng natitirang balanse ay tahimik — walang resibo,
// walang kumpirmasyon na bayad na nang buo.
//
// Tatlong anyo ito, depende sa konteksto:
//   1. unang bayad  → "Booking Confirmed" (dating tanging behavior)
//   2. may balanse pa → "Payment Received" + natitirang balanse
//   3. wala nang balanse → "Fully Paid"
class BookingConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        // Ang halaga ng bayad na KATATANGGAP lang — iba ito sa
        // $booking->amount_paid, na kabuuan na ng lahat ng bayad.
        // null kapag hindi alam ng caller (hal. legacy call site).
        public ?float $amountPaid = null,
        // true lang kapag ang bayad na ito ang nag-flip sa booking
        // mula 'pending' patungong 'confirmed'.
        public bool $isFirstConfirmation = true,
    ) {}

    public function build()
    {
        return $this
            ->subject($this->subjectLine())
            ->view('emails.booking_confirmed')
            ->with(['summary' => $this->summary()]);
    }

    private function subjectLine(): string
    {
        $ref = $this->booking->booking_ref;

        if ($this->isFirstConfirmation) {
            return "Booking Confirmed — {$ref} | Villa Elena Resort";
        }

        return $this->summary()['fully_paid']
            ? "Fully Paid — {$ref} | Villa Elena Resort"
            : "Payment Received — {$ref} | Villa Elena Resort";
    }

    // Buong buod ng bayad, kinuwenta rito (hindi sa Blade) para
    // matestingan nang hiwalay sa pag-render ng email.
    //
    // MAHALAGA: sa oras na mabuo ang mail na ito, tumakbo na ang
    // Booking::recalculateFinancials() sa lahat ng apat na payment
    // path — kaya KASAMA NA sa $booking->amount_paid ang bayad na
    // katatanggap lang. Doon nagmumula ang "Previously Paid":
    // amount_paid MINUS ang kasalukuyang bayad. Kung baligtarin ang
    // pagkakasunod na iyon sa isang bagong payment path, magiging mali
    // ang bilang na ito nang tahimik.
    public function summary(): array
    {
        $total = round((float) $this->booking->total_amount, 2);
        $totalPaid = round((float) $this->booking->amount_paid, 2);

        // Netong nabayaran na ang amount_paid (nabawasan na ito ng
        // anumang refund), kaya sinusundan ito ng balanse.
        $balance = max(0, round($total - $totalPaid, 2));
        $fullyPaid = $balance <= 0;

        $current = is_null($this->amountPaid) ? null : round((float) $this->amountPaid, 2);
        $previous = is_null($current) ? null : max(0, round($totalPaid - $current, 2));

        $rows = [['Total Amount', $total]];

        if (! is_null($current)) {
            // Ang buong punto ng paghahati: kapag may naunang bayad,
            // ang "This Payment: ₱6" katabi ng "Total Paid: ₱12" ay
            // mukhang mali sa guest — parang mas malaki ang binayaran
            // kaysa sa aktwal. Ipinapakita nang hiwalay ang naunang
            // bayad para magkasya ang math: previous + current = total.
            //
            // Kapag ₱0 ang naunang bayad (unang bayad pa lang), walang
            // ganoong pagkalito — laktawan ito, dahil ang
            // "Previously Paid: ₱0.00" ay ingay lang doon.
            if ($previous > 0) {
                $rows[] = ['Previously Paid', $previous];
            }

            // "Final Payment" lang kapag ito na ang pumatay sa balanse
            // AT may naunang bayad; kung hindi, "This Payment".
            $rows[] = [
                $fullyPaid && $previous > 0 ? 'Final Payment' : 'This Payment',
                $current,
            ];
        }

        $rows[] = ['Total Paid', $totalPaid];

        return [
            'rows' => $rows,
            'balance' => $balance,
            'fully_paid' => $fullyPaid,
            'status_label' => $fullyPaid ? 'Fully Paid' : 'Partial Payment',
            'current_payment' => $current,
            'previously_paid' => $previous,
        ];
    }
}
