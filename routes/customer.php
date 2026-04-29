<?php
// ════════════════════════════════════════════════════════════════
//  routes/customer.php  — replace entire file with this
// ════════════════════════════════════════════════════════════════

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\BookingController;
use App\Http\Controllers\Customer\ReviewController as CustomerReviewController;


Route::prefix('my')
    ->name('customer.')
    ->middleware(['auth', 'role:customer'])
    ->group(function () {

    Route::get('/',           [HomeController::class, 'index'])->name('home');
    Route::get('bookings',    [HomeController::class, 'bookings'])->name('bookings');
    Route::get('bookings/{booking}', [HomeController::class, 'bookingDetail'])->name('bookings.show');
    Route::patch('bookings/{booking}/cancel', [HomeController::class, 'cancelBooking'])->name('bookings.cancel');
    Route::get('notifications', [HomeController::class, 'notifications'])->name('notifications');
    Route::get('bookings/{booking}/review',        [CustomerReviewController::class, 'create'])->name('reviews.create');
    Route::post('bookings/{booking}/review',       [CustomerReviewController::class, 'store'])->name('reviews.store');

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