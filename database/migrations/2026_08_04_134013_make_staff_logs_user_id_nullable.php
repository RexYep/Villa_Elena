<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// user_id must accept NULL so the auto check-in/out/cancel scheduled
// command (no authenticated user in console context) can log "System"
// actions via StaffLog::record() without hitting a NOT NULL violation.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE staff_logs MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('staff_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE staff_logs MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('staff_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
