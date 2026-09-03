<?php

namespace App\Helpers;

use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
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
}
