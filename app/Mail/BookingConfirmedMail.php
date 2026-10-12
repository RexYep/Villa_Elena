<?php

namespace App\Mail;

use App\Models\Booking;
use App\Support\GuestEmail;
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
        $summary = $this->summary();
        $variant = $this->variant($summary);

        return $this
            ->subject($variant['subject'].' — '.$this->booking->booking_ref)
            ->view('emails.booking_confirmed')
            ->text('emails.booking_confirmed_text')
            ->with([
                'summary' => $summary,
                'heading' => $variant['heading'],
                'preheader' => $this->preheader(),
                // Ang pahina ng booking ang iisang pupuntahan: doon ang
                // "Pay Now" kapag may balanse pa at puwede pang bayaran, kaya
                // hindi nangangako ang email ng bayarang hindi na bukas.
                'bookingUrl' => GuestEmail::url(route('customer.bookings.show', $this->booking, false)),
                // Ang mga pangungusap ay binubuo rito, hindi sa Blade, dahil
                // dalawang view ang nagpi-print nito (HTML at plain text) at
                // hindi dapat magkaiba ang sinasabi nila.
                'opening' => $this->opening($summary),
                'balanceNote' => $summary['fully_paid'] ? null
                    : '₱'.number_format($summary['balance'], 2).' is still due, to be paid on or before the day of check-in.'
                        .' You can pay it online from your booking page.',
                'policyLines' => $this->isFirstConfirmation ? $this->policyLines() : [],
            ]);
    }

    // Ang unang talata. Isang beses lang binabanggit dito ang halaga ng
    // bayad; ang balanse ay nasa talaan at sa paalala sa ibaba nito, hindi
    // na rin dito — dati ay tatlong beses itong nakasulat sa iisang email.
    private function opening(array $summary): string
    {
        $paid = is_null($summary['current_payment'])
            ? ''
            : ' of ₱'.number_format($summary['current_payment'], 2);

        if ($this->isFirstConfirmation) {
            return "We have received your payment{$paid} and your booking is confirmed"
                .($summary['fully_paid'] ? ' and fully paid' : '')
                .'. We look forward to hosting you.';
        }

        if ($summary['fully_paid']) {
            $which = $summary['previously_paid'] > 0 ? 'final payment' : 'payment';

            return "We have received your {$which}{$paid}. Your booking is now fully paid, and nothing further is due.";
        }

        return "We have received your payment{$paid}. Here is your updated booking summary.";
    }

    // Ang patakarang dapat hawak ng guest sa email na itinatabi niya.
    // Galing sa Booking ang pangungusap at ang mga bilang — hindi kailanman
    // tinitipa ang patakaran sa isang view (tingnan ang
    // Booking::CANCELLATION_POLICY). Ang tungkol sa paglipat ng petsa ay
    // lumalabas lang kapag puwede pa talaga itong gawin.
    private function policyLines(): array
    {
        $lines = [Booking::CANCELLATION_POLICY];

        if ($this->booking->isReschedulable()) {
            $times = $this->booking->reschedulesRemaining();

            $lines[] = 'If your plans change, you can move your booking to another date from your booking page,'
                .' as long as it is at least '.Booking::RESCHEDULE_CUTOFF_DAYS.' days before check-in.'
                .' You can do this '.($times === 1 ? 'once more' : "{$times} times").'.';
        }

        return $lines;
    }

    // Ang subject at ang headline ng tatlong anyo. Magkasama rito para
    // hindi na maulit ang dati: nagbabago ang subject ayon sa anyo pero
    // "Booking Confirmed" pa rin ang <title> at "confirmation email" pa rin
    // ang footer kahit resibo na lang ng pangalawang bayad.
    //
    // Walang pangalan ng resort sa subject: nasa "From" na iyon, katabi
    // mismo ng subject sa inbox.
    private function variant(array $summary): array
    {
        if ($this->isFirstConfirmation) {
            return ['subject' => 'Booking Confirmed', 'heading' => 'Your booking is confirmed'];
        }

        return $summary['fully_paid']
            ? ['subject' => 'Fully Paid', 'heading' => 'Your booking is fully paid']
            : ['subject' => 'Payment Received', 'heading' => 'Payment received'];
    }

    // Ang linyang ipinapakita ng inbox pagkatapos ng subject.
    private function preheader(): string
    {
        $slot = $this->booking->slot_name;

        return ($slot ? $slot.' slot, ' : '')
            .$this->booking->check_in_date->format('D, M j, Y')
            .', check-in '.$this->booking->check_in_time_label.'.';
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

        // Ang balanse ay ang `balance_due` na kinuwenta ng
        // recalculateFinancials() — HINDI `total − amount_paid`. Pareho
        // ang dalawa maliban kapag may goodwill na refund (v7.68), na
        // ibinabawas sa `amount_paid` pero hindi na muling sinisingil.
        // Ang sariling kuwenta rito ay magsasabi sa guest na may utang
        // pa siyang wala naman sa booking page.
        $balance = max(0, round((float) $this->booking->balance_due, 2));
        $fullyPaid = $balance <= 0;

        $current = is_null($this->amountPaid) ? null : round((float) $this->amountPaid, 2);
        $previous = is_null($current) ? null : max(0, round($totalPaid - $current, 2));

        $rows = [];

        // Ipinapakita lang ang presyo bago ang promo kapag MAY promo. Dati
        // ay kabuuan lang ang nasa email, kaya hindi nakikita ng guest na
        // may natipid siya. Ang mga halaga ay ang mismong nasa booking
        // (`base + extras − discount = total`); walang kinukuwenta rito.
        $discount = round((float) $this->booking->discount_amount, 2);

        if ($discount > 0) {
            $slot = $this->booking->slot_name;
            $rows[] = [$slot ? $slot.' rate' : 'Rate', round((float) $this->booking->base_amount, 2)];

            if ((float) $this->booking->extras_amount > 0) {
                $rows[] = ['Add-ons', round((float) $this->booking->extras_amount, 2)];
            }

            // Negatibo: ang view ang naglalagay ng "−" sa unahan ng ₱.
            $rows[] = ['Promo discount', -$discount];
        }

        $rows[] = ['Total Amount', $total];

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
