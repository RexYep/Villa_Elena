<?php
// ════════════════════════════════════════════════════════════════
//  REPLACE ENTIRE: routes/staff.php
// ════════════════════════════════════════════════════════════════

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Staff\FrontdeskController;

Route::prefix('staff')
    ->name('staff.')
    ->middleware(['auth', 'role:staff,admin'])
    ->group(function () {

    // Main frontdesk
    Route::get('/frontdesk',                    [FrontdeskController::class, 'index'])->name('frontdesk');

    // Check in / out
    Route::patch('/checkin/{booking}',          [FrontdeskController::class, 'checkIn'])->name('checkin');
    Route::patch('/checkout/{booking}',         [FrontdeskController::class, 'checkOut'])->name('checkout');

    // Walk-in booking
    Route::get('/walkin',                       [FrontdeskController::class, 'walkinForm'])->name('walkin');
    Route::post('/walkin',                      [FrontdeskController::class, 'storeWalkin'])->name('walkin.store');

    // Payment recording
    Route::post('/bookings/{booking}/payment',  [FrontdeskController::class, 'recordPayment'])->name('payment');

    // Housekeeping
    Route::patch('/tasks/{task}/start',         [FrontdeskController::class, 'startTask'])->name('tasks.start');
    Route::patch('/tasks/{task}/complete',      [FrontdeskController::class, 'completeTask'])->name('tasks.complete');

});