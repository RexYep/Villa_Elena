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

// PayMongo webhook — NO auth, NO CSRF
Route::post('/webhooks/paymongo', [PaymentController::class, 'webhook'])
    ->name('payment.webhook')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Callback ng Send Money — inaabisuhan tayo nito kapag na-settle na
// ang isang refund transfer. Hindi pinagkakatiwalaan ang laman; ang
// tunay na estado ay kinukuha sa isang authenticated na GET (v5.9).
Route::post('/webhooks/paymongo/transfer', [PaymentController::class, 'transferCallback'])
    ->name('payment.webhook.transfer')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Free-tier cron workaround — Render's free plan has no Cron Jobs feature,
// so an external pinger (e.g. cron-job.org) hits this instead of a real
// server cron, to run bookings:auto-checkinout (see routes/console.php).
// Token-gated so randoms can't trigger it.
Route::get('/cron/run-schedule/{token}', function (string $token) {
    abort_unless(
        config('app.cron_secret') && hash_equals((string) config('app.cron_secret'), $token),
        403
    );

    \Illuminate\Support\Facades\Artisan::call('schedule:run');

    return response('ok');
})->name('cron.run-schedule');

// Runs the cache measurements INSIDE the production container and returns
// them as JSON. Render's free plan has no Shell tab, and the production
// Redis (Render Key Value) is internal-only, so this is the only way to find
// out whether Redis actually helps there — a developer machine measures its
// own distance to Aiven and Docker, which answers a different question.
// Same token gate as the cron route; returns timings only, never config
// values or credentials. Safe to delete once the numbers are recorded.
Route::get('/diagnostics/cache/{token}', function (string $token) {
    abort_unless(
        config('app.cron_secret') && hash_equals((string) config('app.cron_secret'), $token),
        403
    );

    return response()->json(
        \App\Services\CacheDiagnostics::measure((int) request()->integer('iterations', 50))
    );
})->name('diagnostics.cache');

// ── Authentication Routes ──────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');

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
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/chatbot', [ChatbotController::class, 'reply'])
    ->name('chatbot.reply')
    ->middleware(['maintenance.check', 'throttle:chatbot']);
