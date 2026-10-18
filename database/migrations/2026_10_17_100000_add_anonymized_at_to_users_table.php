<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a guest's account was deleted (App\Services\AccountErasure).
     *
     * A deleted account keeps its row: every booking and payment reaches the
     * guest through `user_id`, and `bookings.user_id` cascades on delete, so a
     * real DELETE would take the financial records with it. The personal
     * fields are cleared instead, and this column is what tells such a row
     * apart from a walk-in record (also no email) or a suspended account
     * (also `status = 0`).
     *
     * Not `deleted_at`/SoftDeletes on purpose: its global scope would make
     * `$booking->user` NULL on every page that prints the guest of a booking.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
