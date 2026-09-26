<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Portal\ChatbotController;
use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

// ── Public Homepage ────────────────────────────────────────────────────────
// Route::get('/', [HomeController::class, 'index'])->name('home');

// Anonymous, guest-facing browsing/booking pages — gated behind the
// "Maintenance Mode" toggle in Admin Settings (see CheckMaintenanceMode).
// Logged-in admins bypass it; other route groups below (auth, payments,
// webhooks, cron, staff/customer portals) are deliberately left outside
// this group so they keep working during maintenance.
Route::middleware('maintenance.check')->group(function () {
    Route::get('/', [PortalController::class, 'home'])->name('home');
    Route::get('properties/{property}', [PortalController::class, 'propertyDetail'])->name('portal.property');
    Route::get('properties/{property}/price-preview', [PortalController::class, 'pricePreview'])->name('portal.price-preview');
    Route::get('reviews', [PortalController::class, 'reviews'])->name('portal.reviews');

    // Legal pages — naka-link sa footer ng landing page. Dalawa lang sila:
    // nasa loob ng Privacy Policy (#cookies) ang cookie section, dahil
    // strictly-necessary cookies lang ang ginagamit natin.
    Route::get('privacy-policy', [PortalController::class, 'privacy'])->name('portal.privacy');
    Route::get('terms-of-service', [PortalController::class, 'terms'])->name('portal.terms');

    Route::post('contact', [PortalController::class, 'submitContact'])
        ->middleware('throttle:contact')->name('portal.contact.send');

    // Booking form + submit — requires login (handled inside controller)
    Route::get('book/{property}', [PortalController::class, 'bookingForm'])->name('portal.book');
    Route::post('book/{property}', [PortalController::class, 'submitBooking'])
        ->middleware(['auth', 'verified', 'throttle:booking-submit'])
        ->name('portal.book.submit');
    Route::get('booking/confirmed/{booking}', [PortalController::class, 'confirmation'])->name('portal.confirmation');
});

// PayMongo payment routes (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/pay/{booking}', [PaymentController::class, 'showPaymentPage'])->name('payment.page');
    // Naka-throttle: ang bawat POST dito ay dating gumagawa ng bagong
    // PayMongo checkout session. Hinahawakan na ito ng lock at ng
    // session reuse sa createCheckout(), pero walang dahilan para
    // tanggapin ang dose-dosenang session-creation kada minuto mula sa
    // iisang guest — at hindi libre sa gateway ang bawat isa.
    Route::post('/pay/{booking}/checkout', [PaymentController::class, 'createCheckout'])
        ->middleware('throttle:payment-checkout')
        ->name('payment.checkout');
    Route::get('/pay/{booking}/success', [PaymentController::class, 'success'])->name('payment.success');
    // Ang URL na ito ay PIRMADO kapag ginawa ito ng createCheckout()
    // (`URL::temporarySignedRoute`). Sinusuri ang pirma sa LOOB ng
    // controller, hindi sa pamamagitan ng `signed` middleware — kaya
    // walang 403 na sasalubong sa isang guest na may lumang link; ang
    // pirma lang ang nagpapasya kung babaguhin ang `paymongo_session_id`.
    // Tingnan ang PaymentController::cancel() para sa buong dahilan.
    Route::get('/pay/{booking}/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');

    // Tinatanong ito ng checkout at ng "Waiting for Payment" na page
    // tuwing ilang segundo habang naghihintay ng QR Ph settlement.
    // Isang SELECT lang sa booking na pag-aari na ng humihiling —
    // maluwag ang throttle dahil normal na may dalawang tab na bukas
    // ang guest (isa sa checkout, isa sa e-wallet), at ang mawalan ng
    // sagot dito ay mangangahulugang hindi na nila malalaman na bayad
    // na sila.
    Route::get('/pay/{booking}/status', [PaymentController::class, 'status'])
        ->middleware('throttle:payment-status')
        ->name('payment.status');
});

// The PayMongo webhooks used to live here too. They moved to routes/cron.php
// alongside the cron endpoints, for the same reason: PayMongo is a machine, it
// discards the cookie, and sitting in the `web` group meant a `sessions` row
// and a Set-Cookie on every single delivery (measured).
//
// The cron and diagnostics routes used to live here. They moved to
// routes/cron.php, which is registered WITHOUT the `web` group — they are
// called by a machine, and a session row per ping was pure waste. The secret
// also moved out of the URL path and into the `X-Cron-Secret` header, because
// a path segment ends up in access logs, in Render's log stream and in the
// cron service's own history. See routes/cron.php.

// ── Authentication Routes ──────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');

    // Ang IISANG patutunguhan ng register(), bago pa man malaman kung may
    // account na ang address o wala. Walang `auth` dito nang sadya —
    // walang awtomatikong login na ngayon ang pagpaparehistro, dahil ang
    // pagkakaroon ng session ang magiging sagot sa mismong tanong na
    // itinatago natin. Tingnan ang AuthController::register().
    Route::get('/register/check-your-email', [AuthController::class, 'registerPending'])
        ->name('register.pending');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:password-email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update')->middleware('throttle:password-reset');

    Route::get('/two-factor/verify', [AuthController::class, 'showTwoFactor'])->name('two-factor.verify');
    Route::post('/two-factor/verify', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:two-factor-verify');
    Route::post('/two-factor/resend', [AuthController::class, 'resendTwoFactor'])->name('two-factor.resend')->middleware('throttle:two-factor-resend');
});

// ── Logout (requires auth) ─────────────────────────────────────────────────
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ── Email Verification ─────────────────────────────────────────────────────
// Notice + resend still require auth (user must be logged in to request resend)
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [AuthController::class, 'verifyNotice'])
        ->name('verification.notice');

    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:verification-send')
        ->name('verification.send');
});

// The verify link itself must work WITHOUT auth middleware because the user
// clicks it from Gmail where they may not have an active session.
// We use the signed URL + id/hash to securely identify and verify the user.
//
// The third `throttle` argument is a KEY PREFIX, and leaving it off is not
// cosmetic: a numeric throttle keys its counter by the user id alone (or
// domain+IP for a guest) with no route in it, so every `throttle:N,1` in the
// app shared ONE bucket — this 6/min cap was being spent by admin dashboard
// polling, and vice versa. Same bug the named limiters fixed for the payment
// routes; the prefix is the numeric equivalent. Every numeric throttle in
// this project carries one, and no two may match.
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1,verify-email'])
    ->name('verification.verify');

Route::post('/chatbot', [ChatbotController::class, 'reply'])
    ->name('chatbot.reply')
    ->middleware(['maintenance.check', 'throttle:chatbot']);
