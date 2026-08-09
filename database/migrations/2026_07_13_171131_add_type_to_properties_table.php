<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Default 2:00 PM check-in / 12:00 PM (noon) check-out
            // bilang fallback lang, pero editable/settable per booking.
            $table->time('check_in_time')->default('14:00:00')->after('check_in_date');
            $table->time('check_out_time')->default('12:00:00')->after('check_out_date');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['check_in_time', 'check_out_time']);
        });
    }
};