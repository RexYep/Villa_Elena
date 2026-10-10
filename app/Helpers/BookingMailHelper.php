<?php

namespace App\Helpers;

use App\Mail\BookingConfirmedMail;
use App\Mail\RefundNoticeMail;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
                    rtrim((string) config('app.url'), '/') . '/' . ltrim($link, '/'),
                    $actionNote,
                ));
            } catch (\Throwable $e) {
                Log::error("Failed sending refund email ({$heading}) for {$booking->booking_ref}: ".$e->getMessage());
            }
        });
    }
}
