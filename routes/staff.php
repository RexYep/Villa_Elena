<?php
// ════════════════════════════════════════════════════════════════
//  REPLACE ENTIRE: routes/staff.php
// ════════════════════════════════════════════════════════════════

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Staff\FrontDeskController;

Route::prefix('staff')
    ->name('staff.')
    ->middleware(['auth', 'role:staff,admin'])
    ->group(function () {

    // Main frontdesk
    Route::get('/frontdesk',                    [FrontDeskController::class, 'index'])->name('frontdesk');

    // Slot-by-slot availability grid (Day/Night per date)
    Route::get('/availability',                 [FrontDeskController::class, 'availability'])->name('availability');

    // Check in / out
    Route::patch('/checkin/{booking}',          [FrontDeskController::class, 'checkIn'])->name('checkin');
    Route::patch('/checkout/{booking}',         [FrontDeskController::class, 'checkOut'])->name('checkout');

    // Walk-in booking
    Route::get('/walkin',                       [FrontDeskController::class, 'walkinForm'])->name('walkin');
    Route::get('/walkin/quote',                 [FrontDeskController::class, 'priceQuote'])->name('walkin.quote');
    Route::post('/walkin',                      [FrontDeskController::class, 'storeWalkin'])->name('walkin.store');

    // Payment recording
    Route::post('/bookings/{booking}/payment',  [FrontDeskController::class, 'recordPayment'])->name('payment');

    // Housekeeping
    Route::patch('/tasks/{task}/start',         [FrontDeskController::class, 'startTask'])->name('tasks.start');
    Route::patch('/tasks/{task}/complete',      [FrontDeskController::class, 'completeTask'])->name('tasks.complete');

});