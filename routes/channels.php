<?php

use Illuminate\Support\Facades\Broadcast;

// A user may only listen on their own private notification channel.
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
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
