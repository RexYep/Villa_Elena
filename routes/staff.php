<?php

// ════════════════════════════════════════════════════════════════
//  REPLACE ENTIRE: routes/staff.php
// ════════════════════════════════════════════════════════════════

use App\Http\Controllers\Staff\FrontDeskController;
use App\Http\Controllers\Staff\HousekeepingController;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')
    ->name('staff.')
    ->middleware(['auth', 'role:staff,admin'])
    ->group(function () {

        // Main frontdesk
        Route::get('/frontdesk', [FrontDeskController::class, 'index'])->name('frontdesk');

        // Slot-by-slot availability grid (Day/Night per date)
        Route::get('/availability', [FrontDeskController::class, 'availability'])->name('availability');
        // Ang grid lang, para sa kusang pag-update (v7.10)
        Route::get('/availability/grid', [FrontDeskController::class, 'availabilityGrid'])
            ->middleware('throttle:60,1,staff-availability-grid')
            ->name('availability.grid');

        // Stats row + Today list lang, para sa kusang pag-update (v7.19) —
        // ito ang nagpapakita ng auto check-in/out nang hindi nagre-refresh.
        Route::get('/frontdesk/today', [FrontDeskController::class, 'todayLive'])
            ->middleware('throttle:60,1,staff-today')
            ->name('frontdesk.today');

        // Check in / out
        Route::patch('/checkin/{booking}', [FrontDeskController::class, 'checkIn'])->name('checkin');
        Route::patch('/checkout/{booking}', [FrontDeskController::class, 'checkOut'])->name('checkout');

        // Walk-in booking
        Route::get('/frontdesk/housekeeping', [FrontDeskController::class, 'housekeepingLive'])
            ->middleware('throttle:60,1,staff-housekeeping-live')
            ->name('frontdesk.housekeeping');
        Route::get('/walkin', [FrontDeskController::class, 'walkinForm'])->name('walkin');
        Route::get('/walkin/quote', [FrontDeskController::class, 'priceQuote'])->name('walkin.quote');
        Route::post('/walkin', [FrontDeskController::class, 'storeWalkin'])
            ->middleware('throttle:walkin-create')
            ->name('walkin.store');

        // Payment recording
        Route::post('/bookings/{booking}/payment', [FrontDeskController::class, 'recordPayment'])->name('payment');

        // Housekeeping — task mula sa admin, at ulat ng problema (v7.11)
        Route::patch('/tasks/{task}/start', [HousekeepingController::class, 'startTask'])->name('tasks.start');
        Route::patch('/tasks/{task}/complete', [HousekeepingController::class, 'completeTask'])->name('tasks.complete');
        Route::post('/reports', [HousekeepingController::class, 'storeReport'])
            ->middleware('throttle:issue-report')
            ->name('reports.store');
        Route::patch('/reports/{report}/start', [HousekeepingController::class, 'startReport'])->name('reports.start');
        Route::patch('/reports/{report}/complete', [HousekeepingController::class, 'completeReport'])->name('reports.complete');

    });
