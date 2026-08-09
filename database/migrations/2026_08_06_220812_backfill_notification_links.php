<?php

use App\Models\Booking;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Every notification created before the `link` column existed has
     * link=null — clicking one just redirects back to the notifications
     * list (looks like "nothing happens"). Most titles/messages already
     * embed a booking_ref (VE-XXXXXXXX) or are clearly review-related, so
     * a real destination can be recovered for the large majority of them.
     */
    public function up(): void
    {
        // get() rather than chunk() — chunk() paginates by OFFSET, and each
        // update() here removes the row from the whereNull('link') result
        // set, shifting the offset and silently skipping later rows.
        Notification::whereNull('link')->with('user')->get()->each(function ($notif) {
            $isAdmin = $notif->user && $notif->user->role === 'admin';
            $text    = $notif->title . ' ' . $notif->message;
            $link    = null;

            if (preg_match('/VE-[A-Z0-9]{6,10}/', $text, $m)) {
                $booking = Booking::where('booking_ref', $m[0])->first();
                if ($booking) {
                    $link = $isAdmin
                        ? route('admin.bookings.show', $booking, false)
                        : route('customer.bookings.show', $booking, false);
                }
            } elseif (str_contains($notif->title, 'Review')) {
                $link = $isAdmin ? route('admin.reviews.index', [], false) : route('customer.reviews.index', [], false);
            } elseif ($notif->title === 'New Guest Registered') {
                $link = route('admin.users.index', [], false);
                if (preg_match('/\(([^)]+@[^)]+)\)/', $notif->message, $m2)) {
                    $user = User::where('email', $m2[1])->first();
                    if ($user) {
                        $link = route('admin.users.show', $user, false);
                    }
                }
            }

            if ($link) {
                $notif->update(['link' => $link]);
            }
        });
    }

    /**
     * Not reversible — we can't tell which links were backfilled here
     * versus set normally afterward, and there's nothing unsafe about
     * leaving them populated.
     */
    public function down(): void
    {
        //
    }
};
