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
use App\Http\Controllers\Admin\PrescriptiveController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ProfileController;


Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Booking lookup for payment modal (AJAX)
Route::get('bookings/lookup', function (\Illuminate\Http\Request $request) {
    $booking = \App\Models\Booking::where('booking_ref', strtoupper(trim((string) $request->ref)))
        ->with(['user:id,full_name', 'property:id,property_name'])
        ->first();
 
    if (!$booking) return response()->json(['error' => 'Not found'], 404);
 
    return response()->json([
        'id'       => $booking->id,
        'guest'    => $booking->user->full_name,
        'property' => $booking->property->property_name,
        'balance'  => $booking->balance_due,
        // Para masabi agad ng modal na cancelled ang booking — bago pa
        // tumanggi ang server.
        'status'   => $booking->status,
    ]);
    })->name('bookings.lookup');

    // Bookings
    Route::resource('bookings', BookingController::class);
    Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
    Route::patch('bookings/{booking}/extend', [BookingController::class, 'extendStay'])->name('bookings.extend');
    Route::post('bookings/{booking}/payment', [BookingController::class, 'recordPayment'])->name('bookings.payment');
    Route::post('bookings/{booking}/extras', [BookingController::class, 'storeExtra'])->name('bookings.extras.store');
    Route::delete('bookings/{booking}/extras/{extra}', [BookingController::class, 'destroyExtra'])->name('bookings.extras.destroy');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');

    // Properties
    Route::resource('properties', PropertyController::class);
    Route::post('properties/{property}/block-dates', [PropertyController::class, 'blockDates'])->name('properties.block');
    Route::delete('properties/images/{image}', [PropertyController::class, 'deleteImage'])->name('properties.images.destroy');

    // Promotions (seasonal discounts) — admin-only sa layunin: ang promo
    // ay direktang pagbaba ng kita, kaya hindi ito inilalagay sa staff
    // portal kahit awtomatikong nakikinabang doon ang walk-in booking.
    Route::get('promotions',                       [PromotionController::class, 'index'])->name('promotions.index');
    Route::get('promotions/create',                [PromotionController::class, 'create'])->name('promotions.create');
    Route::post('promotions',                      [PromotionController::class, 'store'])->name('promotions.store');
    Route::get('promotions/{promotion}/edit',      [PromotionController::class, 'edit'])->name('promotions.edit');
    Route::put('promotions/{promotion}',           [PromotionController::class, 'update'])->name('promotions.update');
    Route::patch('promotions/{promotion}/toggle',  [PromotionController::class, 'toggle'])->name('promotions.toggle');
    Route::post('promotions/{promotion}/notify',   [PromotionController::class, 'notify'])->name('promotions.notify');
    Route::delete('promotions/{promotion}',        [PromotionController::class, 'destroy'])->name('promotions.destroy');

    // Users
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle');

    // Payments
    Route::get('payments',                      [PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments',                     [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments/{payment}',            [PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/refund',    [PaymentController::class, 'refund'])->name('payments.refund');
    Route::patch('payments/{payment}/paid-out', [PaymentController::class, 'markRefundPaidOut'])->name('payments.paidOut');
    // Ipinapasok ng admin ang refund destination para sa guest — kapag
    // nakuha ito sa text o tawag sa halip na sa form (v5.9).
    Route::put('payments/{payment}/destination', [PaymentController::class, 'setRefundDestination'])->name('payments.destination');
    // Ipinapadala ng sistema mismo ang refund sa PayMongo (v5.9 Phase 4).
    // Hindi ito kapalit ng paidOut sa itaas — nananatili iyon bilang
    // fallback para sa cash at para sa mga bigong transfer.
    Route::post('payments/{payment}/send', [PaymentController::class, 'sendRefundTransfer'])->name('payments.send');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('reports/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');

    // Reviews
Route::get('reviews',                          [ReviewController::class, 'index'])->name('reviews.index');
Route::patch('reviews/{review}/approve',       [ReviewController::class, 'approve'])->name('reviews.approve');
Route::patch('reviews/{review}/reject',        [ReviewController::class, 'reject'])->name('reviews.reject');
Route::post('reviews/{review}/reply',          [ReviewController::class, 'reply'])->name('reviews.reply');
Route::delete('reviews/{review}',             [ReviewController::class, 'destroy'])->name('reviews.destroy');
 

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    // My Account (the admin's own profile — separate from resort-wide Settings)
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Insights / Forecast — ang GET ay BUMABASA lang ng huling naitabing
    // report (`AiReportStore`); ang POST refresh lang ang tumatawag sa Groq.
    // Kaya POST: ang dating "Refresh" ay isang <a href> papunta sa mismong
    // GET, kaya walang pinagkaiba ang pagpindot nito sa karaniwang
    // pag-navigate — bawat bisita ay isang request sa Groq. Tingnan ang
    // `prescriptive.regenerate` sa ibaba: matagal nang ganito ang hugis noon.
    Route::get('/insights', [InsightsController::class, 'index'])->name('insights.index');
    Route::post('/insights/refresh', [InsightsController::class, 'refresh'])->name('insights.refresh');

    Route::get('/forecast', [ForecastController::class, 'index'])->name('forecast.index');
    Route::post('/forecast/refresh', [ForecastController::class, 'refresh'])->name('forecast.refresh');

    // Prescriptive Analytics — ADMIN LANG, tulad ng Promotions. Ang mga
    // aksyon dito ang gumagawa ng totoong Discount/AvailabilityBlock, at
    // inilalantad ng page ang panloob na datos ng negosyo (mahihinang
    // petsa, tinatayang kita), kaya wala ito sa staff portal.
    Route::get('prescriptive',                          [PrescriptiveController::class, 'index'])->name('prescriptive.index');
    // GET at read-only — walang isinusulat ang simulator, kaya ligtas
    // itong i-refresh at i-bookmark.
    Route::get('prescriptive/simulate',                 [PrescriptiveController::class, 'simulate'])->name('prescriptive.simulate');
    Route::get('prescriptive/accuracy',                 [PrescriptiveController::class, 'accuracy'])->name('prescriptive.accuracy');
    Route::post('prescriptive/regenerate',              [PrescriptiveController::class, 'regenerate'])->name('prescriptive.regenerate');
    Route::post('prescriptive/{recommendation}/apply',  [PrescriptiveController::class, 'apply'])->name('prescriptive.apply');
    Route::post('prescriptive/{recommendation}/dismiss', [PrescriptiveController::class, 'dismiss'])->name('prescriptive.dismiss');

    Route::get('search', [DashboardController::class, 'search'])->name('search');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/mark-read', [NotificationController::class, 'markAllRead'])->name('notifications.markRead');


// Calendar
Route::get('calendar',                          [CalendarController::class, 'index'])->name('calendar.index');
Route::get('calendar/events',                   [CalendarController::class, 'events'])->name('calendar.events');
Route::patch('calendar/bookings/{booking}/move',[CalendarController::class, 'moveBooking'])->name('calendar.move');
Route::post('calendar/block',                   [CalendarController::class, 'quickBlock'])->name('calendar.block');
Route::delete('calendar/blocks/{block}',        [CalendarController::class, 'deleteBlock'])->name('calendar.deleteBlock');
});