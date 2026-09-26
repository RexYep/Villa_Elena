<?php

use Illuminate\Support\Facades\Broadcast;

// A user may only listen on their own private notification channel.
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/*
|--------------------------------------------------------------------------
| Staff and admin dashboards
|--------------------------------------------------------------------------
|
| These two were `Channel` (PUBLIC) until v7.24, which meant no callback ran
| and none could: a public Pusher channel is readable by anyone holding the
| app key, and the app key is a CLIENT credential — it is rendered into the
| page, including the unauthenticated property page.
|
| What was going over them is not a ping. `BookingCreated` carries the guest's
| full name, the booking ref, the dates and `total_amount`; `PaymentReceived`
| carries the guest's name, the amount and the payment method; the frontdesk
| messages name guests and what was done for them.
|
| Note `property-availability.{id}` is deliberately left PUBLIC and has no
| callback here — see the comment on PropertyAvailabilityChanged. It carries
| only a blocked date range, which every visitor's page is already
| server-rendered with. That is the test these two failed.
|
| `$user` is only ever an authenticated user: the /broadcasting/auth route is
| registered with ['web', 'auth'] in bootstrap/app.php, so an unauthenticated
| subscribe is rejected before it reaches these closures.
*/
Broadcast::channel('admin-dashboard', function ($user) {
    return $user->role === 'admin' && $user->isActive();
});

Broadcast::channel('staff-frontdesk', function ($user) {
    return in_array($user->role, ['staff', 'admin'], true) && $user->isActive();
});

// Ang guest lang na may-ari ng booking ang makakapakinig sa payment
// channel nito. Dito ipinapadala ang abiso na may naitalang bayad,
// para mag-update nang kusa ang checkout/success page habang naghihintay
// ang guest — asynchronous ang QR Ph, kaya maaaring dumating ang bayad
// sa pamamagitan ng webhook nang matagal pagkatapos umalis ng guest sa
// PayMongo.
//
// Ang event na ito ay PAALALA lang, hindi awtoridad: ang page ay
// nagbabasa pa rin ng tunay na estado sa payment.status endpoint.
Broadcast::channel('booking-payment.{bookingId}', function ($user, $bookingId) {
    return \App\Models\Booking::where('id', $bookingId)
        ->where('user_id', $user->id)
        ->exists();
});
