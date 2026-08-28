<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes WHO cancelled a booking — a guest, an admin, or the
     * system sweeper (AutoCheckInOutBookings::cancelStalePendingBookings,
     * unpaid-past-the-hold-window). Before this, every cancellation looked
     * identical (status='cancelled' + cancellation_reason text), so the
     * only way to tell a guest's voluntary cancel apart from an unpaid
     * auto-cancel was to string-match the reason. That distinction backs
     * the booking-creation cooldown in Booking::bookingCooldownEndsAt() —
     * it must count repeated non-payment holds specifically, not
     * legitimate cancellations (which already have their own penalty via
     * Booking::hasExcessiveCancellations()).
     *
     * Existing rows start NULL — there's no reliable way to backfill who
     * cancelled bookings that predate this column, and NULL correctly
     * excludes them from the new cooldown count rather than guessing.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('cancelled_by', ['guest', 'admin', 'system'])
                ->nullable()
                ->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('cancelled_by');
        });
    }
};
