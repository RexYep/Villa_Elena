<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Both FrontDeskController::checkIn()/checkOut() and the scheduled
// AutoCheckInOutBookings command already write 'actual_check_in' /
// 'actual_check_out' via Booking::update() — but the columns never
// existed, so the values were silently dropped by Eloquent's
// mass-assignment guard before ever reaching SQL (no crash, just a
// permanent no-op). This adds the columns so that write actually lands.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('actual_check_in')->nullable()->after('check_in_time');
            $table->timestamp('actual_check_out')->nullable()->after('check_out_time');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['actual_check_in', 'actual_check_out']);
        });
    }
};
