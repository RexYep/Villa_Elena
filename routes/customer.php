<?php
// ════════════════════════════════════════════════════════════════
//  routes/customer.php  — replace entire file with this
// ════════════════════════════════════════════════════════════════

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\BookingController;
use App\Http\Controllers\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;


Route::prefix('my')
    ->name('customer.')
    ->middleware(['auth', 'role:customer', 'verified'])
    ->group(function () {

    Route::get('/',           [HomeController::class, 'index'])->name('home');
    Route::get('bookings',    [HomeController::class, 'bookings'])->name('bookings');
    Route::get('bookings/{booking}', [HomeController::class, 'bookingDetail'])->name('bookings.show');
    Route::patch('bookings/{booking}/cancel', [HomeController::class, 'cancelBooking'])->name('bookings.cancel');
    Route::get('notifications', [HomeController::class, 'notifications'])->name('notifications');
    Route::get('notifications/{notification}/open', [HomeController::class, 'openNotification'])->name('notifications.open');
    Route::get('bookings/{booking}/review',        [CustomerReviewController::class, 'create'])->name('reviews.create');
    Route::post('bookings/{booking}/review',       [CustomerReviewController::class, 'store'])->name('reviews.store');
    Route::get('bookings/{booking}/reschedule',    [BookingController::class, 'edit'])->name('bookings.reschedule');
    Route::patch('bookings/{booking}/reschedule',  [BookingController::class, 'update'])->name('bookings.reschedule.update');

    Route::get('reviews',             [CustomerReviewController::class, 'index'])->name('reviews.index');
    Route::get('reviews/{review}/edit', [CustomerReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('reviews/{review}',    [CustomerReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/{review}', [CustomerReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('payments', [CustomerPaymentController::class, 'index'])->name('payments.index');

    Route::get('profile',          [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('profile',       [ProfileController::class, 'deactivate'])->name('profile.deactivate');

    Route::put('profile/2fa',                 [ProfileController::class, 'toggleTwoFactor'])->name('profile.2fa.toggle');
    Route::delete('profile/devices/{device}', [ProfileController::class, 'removeTrustedDevice'])->name('profile.devices.destroy');

});

// ════════════════════════════════════════════════════════════════
//  routes/web.php  — ADD these public routes (no auth needed)
//  Paste BEFORE the auth routes section
// ════════════════════════════════════════════════════════════════

// Route::get('/',                [App\Http\Controllers\Portal\PortalController::class, 'home'])->name('home');
// Route::get('properties',       [App\Http\Controllers\Portal\PortalController::class, 'properties'])->name('portal.properties');
// Route::get('properties/{property}', [App\Http\Controllers\Portal\PortalController::class, 'propertyDetail'])->name('portal.property');
// Route::get('book/{property}',  [App\Http\Controllers\Portal\PortalController::class, 'bookingForm'])->name('portal.book');
// Route::post('book/{property}', [App\Http\Controllers\Portal\PortalController::class, 'submitBooking'])->name('portal.book.submit');