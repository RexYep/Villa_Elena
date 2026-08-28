<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reschedules were previously unlimited and untracked — there was no
     * way to even count how many times a booking had been moved (short of
     * parsing StaffLog rows). This column backs the per-booking cap in
     * Booking::MAX_RESCHEDULES.
     *
     * Existing bookings start at 0, which is correct-by-default: they get
     * a fresh allowance rather than being retroactively locked out.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('reschedule_count')
                ->default(0)
                ->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('reschedule_count');
        });
    }
};
