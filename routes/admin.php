<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\InsightsController;
use App\Http\Controllers\Admin\ForecastController;
use App\Http\Controllers\Admin\CalendarController;


Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Booking lookup for payment modal (AJAX)
Route::get('bookings/lookup', function (\Illuminate\Http\Request $request) {
    $booking = \App\Models\Booking::where('booking_ref', strtoupper($request->ref))
        ->with(['user:id,full_name', 'property:id,property_name'])
        ->first();
 
    if (!$booking) return response()->json(['error' => 'Not found'], 404);
 
    return response()->json([
        'id'       => $booking->id,
        'guest'    => $booking->user->full_name,
        'property' => $booking->property->property_name,
        'balance'  => $booking->balance_due,
    ]);
    })->name('bookings.lookup');

    // Bookings
    Route::resource('bookings', BookingController::class);
    Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
    Route::post('bookings/{booking}/payment', [BookingController::class, 'recordPayment'])->name('bookings.payment');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');

    // Properties
    Route::resource('properties', PropertyController::class);
    Route::post('properties/{property}/block-dates', [PropertyController::class, 'blockDates'])->name('properties.block');
    Route::delete('properties/images/{image}', [PropertyController::class, 'deleteImage'])->name('properties.images.destroy');

    // Users
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle');

    // Payments
    Route::get('payments',                      [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments',                     [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments/{payment}',            [PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/refund',    [PaymentController::class, 'refund'])->name('payments.refund');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    // Reviews
Route::get('reviews',                          [ReviewController::class, 'index'])->name('reviews.index');
Route::patch('reviews/{review}/approve',       [ReviewController::class, 'approve'])->name('reviews.approve');
Route::patch('reviews/{review}/reject',        [ReviewController::class, 'reject'])->name('reviews.reject');
Route::post('reviews/{review}/reply',          [ReviewController::class, 'reply'])->name('reviews.reply');
Route::delete('reviews/{review}',             [ReviewController::class, 'destroy'])->name('reviews.destroy');
 

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    //Insights
    Route::get('/insights', [InsightsController::class, 'index'])->name('insights.index');

    // Forecast
    Route::get('/forecast', [ForecastController::class, 'index'])->name('forecast.index');

    Route::get('search', [DashboardController::class, 'search'])->name('search');
    Route::post('notifications/mark-read', [DashboardController::class, 'markNotificationsRead'])->name('notifications.markRead');


// Calendar
Route::get('calendar',                          [CalendarController::class, 'index'])->name('calendar.index');
Route::get('calendar/events',                   [CalendarController::class, 'events'])->name('calendar.events');
Route::patch('calendar/bookings/{booking}/move',[CalendarController::class, 'moveBooking'])->name('calendar.move');
Route::post('calendar/block',                   [CalendarController::class, 'quickBlock'])->name('calendar.block');
Route::delete('calendar/blocks/{block}',        [CalendarController::class, 'deleteBlock'])->name('calendar.deleteBlock');
});