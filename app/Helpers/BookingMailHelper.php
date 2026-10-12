<?php

namespace App\Helpers;

use App\Mail\BookingConfirmedMail;
use App\Mail\BookingNoticeMail;
use App\Mail\RefundNoticeMail;
use App\Models\Booking;
use App\Support\GuestEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

use function Illuminate\Support\defer;

// Iisang pinagmumulan ng "may natanggap na bayad, padalhan ang guest ng
// email". Dati, nasa loob lang ito ng PaymentController (ang PayMongo
// path), at naka-gate pa sa unang bayad — kaya:
//   * ang pagbabayad ng balanse online ay walang email; at
//   * ang tatlong manwal na path (admin at staff) ay WALANG email kahit
//     kailan, pati na ang unang bayad.
// Dito na dumadaan ang lahat ng apat, kaya hindi na sila puwedeng
// mag-iba-iba muli.
class BookingMailHelper
{
    public static function paymentRecorded(Booking $booking, float $amount, bool $wasPending): void
    {
        $booking = $booking->fresh(['user', 'property']);

        // Ang mga walk-in ay may ginagawang account, pero huwag nang
        // ipagpalagay — mas mabuting laktawan kaysa mag-500 sa isang
        // path na kaka-record lang ng totoong pera.
        if (! $booking || ! $booking->user || ! $booking->user->email || ! $booking->property) {
            return;
        }

        // OPTIONAL na email ito (hindi katulad ng 2FA / verification /
        // password reset, na laging ipinapadala) — kaya ang guest mismo
        // ang nagdedesisyon dito sa My Account → Notifications.
        if (! $booking->user->email_notifications_enabled) {
            return;
        }

        try {
            Mail::to($booking->user->email)->send(
                new BookingConfirmedMail($booking, $amount, $wasPending)
            );
        } catch (\Exception $e) {
            // Hindi dapat i-fail ang buong request kung may isyu ang
            // email delivery — naka-log lang, dahil matagumpay naman
            // talaga ang bayad.
            Log::error("Failed sending booking payment email for {$booking->booking_ref}: ".$e->getMessage());
        }
    }

    /**
     * Ang email na katapat ng isang abiso tungkol sa refund (v7.68).
     *
     * Tinatawag ito ng mga guest-facing na refund preset ng
     * NotificationHelper, na may PAREHONG pamagat at pangungusap — kaya
     * hindi puwedeng magkaiba ang sinasabi ng kampana at ng email.
     *
     * Tatlong bagay ang sinasadya rito:
     *
     *  - `DB::afterCommit()`. Ang isa sa mga tumatawag
     *    (Admin\PaymentController::refund()) ay nasa loob ng isang
     *    transaction na may hawak na lock. Ang email ay isang tawag sa
     *    labas na tumatagal ng ilang segundo, at ang refund na
     *    na-rollback ay hindi dapat naipaalam na. Kapag walang
     *    transaction, tumatakbo ito agad.
     *
     *  - `\Throwable`, hindi `\Exception`. Dumadaan dito ang callback ng
     *    PayMongo kapag dumating ang isang transfer; ang email na hindi
     *    naipadala ay hindi dapat makasira sa pagsasara ng refund.
     *
     *  - Sinusunod ang `email_notifications_enabled`, gaya ng email ng
     *    bayad sa itaas. Nananatili ang in-app na abiso anuman ang
     *    setting na iyon.
     *
     * Ang `$link` ay ang RELATIVE na link ng abiso; ginagawa itong
     * absolute rito mula sa APP_URL — hindi sa host ng kasalukuyang
     * request, na maaaring ang address na ginamit ng admin at hindi ang
     * maaabot ng guest.
     */
    public static function refundNotice(
        Booking $booking,
        string $heading,
        string $body,
        float $amount,
        string $link,
        string $actionLabel,
        ?string $actionNote = null
    ): void {
        DB::afterCommit(function () use ($booking, $heading, $body, $amount, $link, $actionLabel, $actionNote) {
            try {
                $user = $booking->user;

                if (! $user || ! $user->email || ! $user->email_notifications_enabled) {
                    return;
                }

                Mail::to($user->email)->send(new RefundNoticeMail(
                    $booking,
                    $heading,
                    $body,
                    $amount,
                    $actionLabel,
                    GuestEmail::url($link),
                    $actionNote,
                ));
            } catch (\Throwable $e) {
                Log::error("Failed sending refund email ({$heading}) for {$booking->booking_ref}: ".$e->getMessage());
            }
        });
    }

    /**
     * How many booking-change emails one guest can be sent in a day.
     *
     * A guest can book and cancel an unpaid booking as often as the request
     * limiter allows (30 an hour), and every email here draws on the same
     * Brevo quota as sign-in codes and password resets. Past this number the
     * guest still gets the in-app notice; only the email is skipped.
     */
    public const DAILY_NOTICE_LIMIT = 5;

    /**
     * The email beside an in-app notice that a booking changed without a
     * payment: an unpaid hold ran out, a cancellation, or a move (v7.74).
     *
     * Called by the guest-facing presets in NotificationHelper with the SAME
     * title and sentence as the notice. Like refundNotice() it waits for the
     * commit, catches \Throwable, and obeys `email_notifications_enabled`.
     * Two things differ:
     *
     *  - `defer()`. The email goes out after the response. An expired hold
     *    can be released from inside another guest's booking request
     *    (Booking::reserveSlot()), and that guest must not wait on a call to
     *    the mail provider for someone else's email.
     *
     *  - DAILY_NOTICE_LIMIT, counted per guest across all three kinds.
     *
     * @param  array<int, array{0: string, 1: string, 2?: bool}>  $rows
     */
    public static function bookingNotice(
        Booking $booking,
        string $heading,
        string $body,
        array $rows,
        ?string $note = null
    ): void {
        DB::afterCommit(function () use ($booking, $heading, $body, $rows, $note) {
            defer(function () use ($booking, $heading, $body, $rows, $note) {
                try {
                    $user = $booking->user;

                    if (! $user || ! $user->email || ! $user->email_notifications_enabled) {
                        return;
                    }

                    $key = 'booking-notice-mail:'.$user->id;

                    if (RateLimiter::tooManyAttempts($key, self::DAILY_NOTICE_LIMIT)) {
                        Log::info("Booking email ({$heading}) for {$booking->booking_ref} not sent: guest {$user->id} has had "
                            .self::DAILY_NOTICE_LIMIT.' today. The in-app notice was still created.');

                        return;
                    }

                    RateLimiter::hit($key, 86400);

                    Mail::to($user->email)->send(new BookingNoticeMail(
                        $booking,
                        $heading,
                        $body,
                        $rows,
                        GuestEmail::url(route('customer.bookings.show', $booking, false)),
                        $note,
                    ));
                } catch (\Throwable $e) {
                    Log::error("Failed sending booking email ({$heading}) for {$booking->booking_ref}: ".$e->getMessage());
                }
            });
        });
    }

    /**
     * The booking's slot and dates as table rows for an email. By slot and
     * time, never by number of nights (see "UI conventions" in CLAUDE.md).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function stayRows(Booking $booking): array
    {
        $rows = [];

        if ($booking->slot_name) {
            $rows[] = ['Slot', $booking->slot_name];
        }

        $rows[] = ['Check-in', $booking->check_in_date->format('D, M j, Y').', '.$booking->check_in_time_label];
        $rows[] = ['Check-out', $booking->check_out_date->format('D, M j, Y').', '.$booking->check_out_time_label];

        return $rows;
    }
}
