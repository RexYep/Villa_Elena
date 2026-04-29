<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Staff\FrontDeskController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\ChatbotController;

// ── Public Homepage ────────────────────────────────────────────────────────
//Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/',                          [PortalController::class, 'home'])->name('home');
Route::get('properties/{property}',      [PortalController::class, 'propertyDetail'])->name('portal.property');

// Booking form + submit — requires login (handled inside controller)
Route::get('book/{property}',            [PortalController::class, 'bookingForm'])->name('portal.book');
Route::post('book/{property}',           [PortalController::class, 'submitBooking'])->name('portal.book.submit');
Route::get('booking/confirmed/{booking}',[PortalController::class, 'confirmation'])->name('portal.confirmation');

// PayMongo payment routes (auth required)
Route::middleware('auth')->group(function () {
    Route::get( '/pay/{booking}',          [PaymentController::class, 'showPaymentPage'])->name('payment.page');
    Route::post('/pay/{booking}/checkout', [PaymentController::class, 'createCheckout'])->name('payment.checkout');
    Route::get( '/pay/{booking}/success',  [PaymentController::class, 'success'])->name('payment.success');
    Route::get( '/pay/{booking}/cancel',   [PaymentController::class, 'cancel'])->name('payment.cancel');
});
 
// PayMongo webhook — NO auth, NO CSRF
Route::post('/webhooks/paymongo', [PaymentController::class, 'webhook'])
    ->name('payment.webhook')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
 

// ── Authentication Routes ──────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register']);

    Route::get('/forgot-password',  [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}',  [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password',         [AuthController::class, 'resetPassword'])->name('password.update');
});

// ── Logout (requires auth) ─────────────────────────────────────────────────
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ── Admin Routes ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(base_path('routes/admin.php'));

// ── Staff Routes ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:staff,admin'])
    ->prefix('staff')
    ->name('staff.')
    ->group(base_path('routes/staff.php'));

// ── Customer Routes ────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:customer'])
    ->prefix('my')
    ->name('customer.')
    ->group(base_path('routes/customer.php'));

    //
    Route::post('/chatbot', [ChatbotController::class, 'reply'])->name('chatbot.reply');